<?php

declare(strict_types=1);

use App\Models\OrganizationMembership;
use App\Models\SiteFaq;
use App\Models\User;

it('lets the owner create, reorder and delete an faq entry', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/faq', [
        'question' => 'Como agendar?',
        'answer' => 'Pelo formulário desta página.',
    ])->assertRedirect();

    $faq = SiteFaq::query()->where('question', 'Como agendar?')->firstOrFail();

    $this->actingAs($user)->delete("/settings/site/faq/{$faq->id}")->assertRedirect();

    expect(SiteFaq::query()->find($faq->id))->toBeNull();
});

it('validates required fields', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/faq', [])
        ->assertSessionHasErrors(['question', 'answer']);
});

it('rejects a question or answer made up only of whitespace', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/faq', [
        'question' => '   ',
        'answer' => '   ',
    ])->assertSessionHasErrors(['question', 'answer']);
});

it('lets the owner edit an existing faq entry', function () {
    $user = actingOwnerWithActiveContext();
    $faq = SiteFaq::factory()->create(['question' => 'Pergunta original']);

    $this->actingAs($user)
        ->put("/settings/site/faq/{$faq->id}", [
            'question' => 'Pergunta editada',
            'answer' => 'Resposta editada.',
        ])
        ->assertSessionHasNoErrors();

    expect($faq->fresh()->question)->toBe('Pergunta editada');
});

it('lets the owner activate and deactivate an faq entry', function () {
    $user = actingOwnerWithActiveContext();
    $faq = SiteFaq::factory()->create(['is_active' => true]);

    $this->actingAs($user)->patch("/settings/site/faq/{$faq->id}/toggle")
        ->assertSessionHasNoErrors();
    expect($faq->fresh()->is_active)->toBeFalse();

    $this->actingAs($user)->patch("/settings/site/faq/{$faq->id}/toggle")
        ->assertSessionHasNoErrors();
    expect($faq->fresh()->is_active)->toBeTrue();
});

it('lets the owner reorder faq entries', function () {
    $user = actingOwnerWithActiveContext();
    $first = SiteFaq::factory()->create(['order' => 0]);
    $second = SiteFaq::factory()->create(['order' => 1]);

    $this->actingAs($user)
        ->patch('/settings/site/faq/reorder', ['ids' => [$second->id, $first->id]])
        ->assertSessionHasNoErrors();

    expect($first->fresh()->order)->toBe(1)
        ->and($second->fresh()->order)->toBe(0);
});

it('blocks a user without site.update permission from managing the faq', function () {
    $ctx = ownerActingInOrganization();
    $faq = SiteFaq::factory()->create();
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($ctx['organization'])->for($member)->create();

    $this->actingAs($member)->post('/settings/site/faq', [
        'question' => 'Não deveria funcionar',
        'answer' => 'Não deveria funcionar.',
    ])->assertForbidden();

    $this->actingAs($member)->delete("/settings/site/faq/{$faq->id}")->assertForbidden();
    $this->actingAs($member)->patch("/settings/site/faq/{$faq->id}/toggle")->assertForbidden();
});
