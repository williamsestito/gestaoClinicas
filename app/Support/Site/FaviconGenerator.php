<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Gera os favicons a partir de uma imagem comum (JPEG/PNG/WebP) enviada
 * pelo administrador, usando a extensão GD nativa do PHP — nenhuma
 * dependência de pacote foi adicionada (GD já está disponível no
 * container). A imagem é recortada ao centro para ficar quadrada e
 * redimensionada para cada tamanho recomendado, sempre exportada como PNG
 * (formato universalmente aceito para `<link rel="icon">`, ao contrário do
 * .ico que exigiria uma biblioteca dedicada para gerar com segurança).
 */
class FaviconGenerator
{
    /** @var list<int> */
    private const SIZES = [16, 32, 48, 180, 192];

    public function __construct(private readonly string $disk = 'public') {}

    /**
     * @return array<int, string> tamanho => caminho no disco (chaves
     *                            numéricas: o PHP sempre converte chaves de
     *                            array que parecem inteiros para int)
     */
    public function generate(UploadedFile $file, string $directory = 'site-content/favicons'): array
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('Não foi possível ler a imagem enviada para gerar o favicon.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $cropX = intdiv($width - $side, 2);
        $cropY = intdiv($height - $side, 2);

        $variants = [];

        try {
            foreach (self::SIZES as $size) {
                $canvas = imagecreatetruecolor($size, $size);
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = (int) imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);
                imagecopyresampled($canvas, $source, 0, 0, $cropX, $cropY, $size, $size, $side, $side);

                ob_start();
                imagepng($canvas);
                $contents = (string) ob_get_clean();
                imagedestroy($canvas);

                $path = sprintf('%s/%s-%d.png', $directory, Str::random(32), $size);
                Storage::disk($this->disk)->put($path, $contents);
                $variants[(string) $size] = $path;
            }
        } finally {
            imagedestroy($source);
        }

        return $variants;
    }

    /**
     * @param  array<int, string>|null  $variants
     */
    public function delete(?array $variants): void
    {
        foreach ($variants ?? [] as $path) {
            if (Storage::disk($this->disk)->exists($path)) {
                Storage::disk($this->disk)->delete($path);
            }
        }
    }
}
