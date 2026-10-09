<?php

namespace Tests\Feature;

use App\Mail\NewEnquiryMail;
use App\Models\Admin;
use App\Models\Enquiry;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class EnquiryTest extends TestCase
{
    use RefreshDatabase;

    protected function valid(array $over = []): array
    {
        return array_merge([
            'source' => 'appointment',
            'name' => 'Ravi Kumar',
            'phone' => '+91 98765 43210',
            'email' => 'ravi@example.com',
            'message' => 'Need a checkup',
            'consent' => '1',
            'terms' => '1',
        ], $over);
    }

    protected function submit(array $data)
    {
        return $this->postJson(route('enquiries.store'), $data);
    }

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_valid_enquiry_is_saved(): void
    {
        $this->submit($this->valid())->assertCreated()->assertJsonStructure(['message']);

        $this->assertDatabaseHas('enquiries', ['name' => 'Ravi Kumar', 'source' => 'appointment', 'status' => 'new', 'consent' => 1]);
    }

    public function test_email_and_message_are_optional(): void
    {
        $this->submit($this->valid(['email' => '', 'message' => '', 'source' => 'strip']))->assertCreated();
    }

    public function test_validation_errors_are_returned(): void
    {
        $this->submit(['source' => 'nope', 'phone' => 'abc', 'email' => 'bad'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['source', 'name', 'phone', 'email', 'consent', 'terms']);

        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_honeypot_is_ignored_silently(): void
    {
        $this->submit($this->valid(['website' => 'http://spam']))->assertCreated();

        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_requests_are_throttled(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->submit($this->valid())->assertCreated();
        }

        $this->submit($this->valid())->assertStatus(429);
    }

    public function test_notification_email_is_sent_when_configured(): void
    {
        Mail::fake();
        Setting::put('site.notify_email', 'owner@example.com');

        $this->submit($this->valid())->assertCreated();

        Mail::assertSent(NewEnquiryMail::class, fn ($m) => $m->hasTo('owner@example.com'));
    }

    public function test_no_email_when_not_configured(): void
    {
        Mail::fake();

        $this->submit($this->valid())->assertCreated();

        Mail::assertNothingSent();
    }

    public function test_mail_failure_does_not_lose_the_enquiry(): void
    {
        Setting::put('site.notify_email', 'owner@example.com');
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $this->submit($this->valid())->assertCreated();

        $this->assertDatabaseCount('enquiries', 1);
    }

    public function test_public_pages_have_the_forms_wired(): void
    {
        foreach (['/' => ['appointment', 'popup', 'strip'], '/contact-us' => ['contact'], '/faq' => ['appointment']] as $url => $sources) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('data-enquiry-form', $html);
            $this->assertStringContainsString('name="website"', $html);
            foreach ($sources as $s) {
                $this->assertStringContainsString('name="source" value="'.$s.'"', $html, "$url missing $s");
            }
        }
    }

    public function test_inbox_requires_permission(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.enquiries.index'))->assertForbidden();
    }

    public function test_inbox_lists_filters_and_manages(): void
    {
        $a = Enquiry::create(['source' => 'contact', 'name' => 'Alpha Person', 'phone' => '9999999991', 'consent' => true]);
        $b = Enquiry::create(['source' => 'popup', 'name' => 'Beta Person', 'phone' => '9999999992', 'consent' => true, 'status' => 'read']);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test('pages::admin.enquiries.index')
            ->assertSee('Alpha Person')->assertSee('Beta Person')
            ->set('filter', 'new')->assertSee('Alpha Person')->assertDontSee('Beta Person')
            ->set('filter', '')->set('source', 'popup')->assertSee('Beta Person')->assertDontSee('Alpha Person')
            ->set('source', '')->set('search', 'Alpha')->assertDontSee('Beta Person')
            ->set('search', '')
            ->call('openEnquiry', $a->id)->assertSet('viewingId', $a->id)
            ->call('closeView')
            ->call('toggleRead', $a->id)
            ->call('markAllRead')
            ->call('delete', $b->id);

        $this->assertSame('read', $a->fresh()->status);
        $this->assertDatabaseMissing('enquiries', ['id' => $b->id]);
    }

    public function test_view_marks_as_read_and_mark_all(): void
    {
        $a = Enquiry::create(['source' => 'contact', 'name' => 'Alpha', 'phone' => '9999999991', 'consent' => true]);
        Enquiry::create(['source' => 'contact', 'name' => 'Gamma', 'phone' => '9999999993', 'consent' => true]);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test('pages::admin.enquiries.index')->call('openEnquiry', $a->id);
        $this->assertSame('read', $a->fresh()->status);

        Livewire::test('pages::admin.enquiries.index')->call('markAllRead');
        $this->assertSame(0, Enquiry::unread()->count());
    }

    public function test_site_settings_saves_notify_email(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test('pages::admin.site.index')->set('notify_email', 'bad')->call('save')->assertHasErrors(['notify_email']);
        Livewire::test('pages::admin.site.index')->set('notify_email', 'me@example.com')->call('save')->assertHasNoErrors();

        $this->assertSame('me@example.com', Setting::get('site.notify_email'));
    }
}
