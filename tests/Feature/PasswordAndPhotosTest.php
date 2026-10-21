<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PasswordAndPhotosTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_emails_a_link_to_the_spa_and_resets(): void
    {
        Notification::fake();
        [, $admin] = $this->createFamily();

        $this->postJson('/api/forgot-password', ['email' => $admin->email])->assertOk();
        // Unknown email gets the same answer — no account enumeration.
        $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

        $token = null;
        Notification::assertSentTo($admin, ResetPassword::class, function (ResetPassword $n) use ($admin, &$token) {
            $token = $n->token;
            $mail = $n->toMail($admin);

            return str_contains($mail->actionUrl, '/reset-password/'.$n->token) && str_contains($mail->subject, 'Сброс пароля');
        });

        $this->postJson('/api/reset-password', [
            'token' => $token, 'email' => $admin->email,
            'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-pass-1', $admin->fresh()->password));

        // A token works only once.
        $this->postJson('/api/reset-password', [
            'token' => $token, 'email' => $admin->email,
            'password' => 'another-pass-22', 'password_confirmation' => 'another-pass-22',
        ])->assertStatus(422);
    }

    public function test_admin_can_create_reset_link_only_for_own_family_only_members(): void
    {
        [$family, $admin] = $this->createFamily();
        $papa = $this->addMember($family);

        $url = $this->actingAs($admin)->postJson("/api/family/members/{$papa->id}/reset-link")->assertOk()->json('url');
        $this->assertStringContainsString('/reset-password/', $url);

        // Someone who also belongs to another family must reset on their own.
        [$otherFamily] = $this->createFamily('Другие');
        FamilyMember::create(['family_id' => $otherFamily->id, 'user_id' => $papa->id, 'role' => 'admin']);
        $this->actingAs($admin)->postJson("/api/family/members/{$papa->id}/reset-link")->assertForbidden();

        // Not a member of the admin's family at all → 404.
        $stranger = User::factory()->create();
        $this->actingAs($admin)->postJson("/api/family/members/{$stranger->id}/reset-link")->assertNotFound();

        // Regular members cannot do it.
        $this->actingAs($papa)->postJson("/api/family/members/{$admin->id}/reset-link")->assertForbidden();
    }

    public function test_photos_are_stored_with_browser_thumbnails(): void
    {
        Storage::fake('local');
        [, $admin] = $this->createFamily();
        $task = $this->actingAs($admin)->postJson('/api/tasks', ['title' => 'Ремонт'])->json();

        $uploaded = $this->actingAs($admin)->post("/api/tasks/{$task['id']}/attachments", [
            'kind' => 'before',
            'files' => [UploadedFile::fake()->create('a.jpg', 400, 'image/jpeg'), UploadedFile::fake()->create('scheme.pdf', 50, 'application/pdf')],
            'thumbs' => [0 => UploadedFile::fake()->create('a-thumb.jpg', 30, 'image/jpeg')],
        ], ['Accept' => 'application/json'])->assertCreated()->json();

        $this->assertStringEndsWith('/thumb', $uploaded[0]['thumb_url']);
        $this->assertNull($uploaded[1]['thumb_url']); // PDF: no preview
        $this->actingAs($admin)->get($uploaded[0]['thumb_url'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $attachment = Attachment::find($uploaded[0]['id']);
        Storage::disk('local')->assertExists($attachment->thumb_path);
        $attachment->delete();
        Storage::disk('local')->assertMissing([$attachment->path, $attachment->thumb_path]);
    }
}
