<?php

namespace Tests\Feature\Admin;

use App\Helper\FeatureFlags;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    public function test_admin_can_create_user_and_reset_password(): void
    {
        $admin = $this->user('admin@example.test');

        $this->actingAs($admin)
            ->withoutMiddleware(VerifyCsrfToken::class)
            ->post(route('users.store'), [
                'name' => 'Operations User',
                'email' => 'ops@example.test',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
            ])
            ->assertRedirect(route('users.index'));

        $created = User::where('email', 'ops@example.test')->firstOrFail();
        $this->assertFalse((bool) $created->is_developer);
        $this->assertTrue(Hash::check('StrongPass123!', $created->password));

        $this->actingAs($admin)
            ->withoutMiddleware(VerifyCsrfToken::class)
            ->put(route('users.update', $created), [
                'name' => 'Operations User',
                'email' => 'ops@example.test',
                'password' => 'NewStrongPass123!',
                'password_confirmation' => 'NewStrongPass123!',
            ])
            ->assertRedirect(route('users.index'));

        $created->refresh();
        $this->assertTrue(Hash::check('NewStrongPass123!', $created->password));
    }

    public function test_admin_cannot_create_developer_account(): void
    {
        $admin = $this->user('admin-two@example.test');

        $this->actingAs($admin)
            ->withoutMiddleware(VerifyCsrfToken::class)
            ->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'Blocked Developer',
                'email' => 'blocked-dev@example.test',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
                'is_developer' => '1',
            ])
            ->assertRedirect(route('users.create'));

        $this->assertDatabaseMissing('users', ['email' => 'blocked-dev@example.test']);
    }

    public function test_admin_cannot_create_reserved_developer_email(): void
    {
        $admin = $this->user('admin-three@example.test');

        $this->actingAs($admin)
            ->withoutMiddleware(VerifyCsrfToken::class)
            ->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'Steven Developer',
                'email' => 'steven@webdesignvr.com',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
            ])
            ->assertRedirect(route('users.create'));

        $this->assertDatabaseMissing('users', ['email' => 'steven@webdesignvr.com']);
    }

    public function test_developer_can_create_developer_account(): void
    {
        $developer = $this->user('lead-dev@example.test', true);

        $this->actingAs($developer)
            ->withoutMiddleware(VerifyCsrfToken::class)
            ->post(route('users.store'), [
                'name' => 'Developer Two',
                'email' => 'developer-two@example.test',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
                'is_developer' => '1',
            ])
            ->assertRedirect(route('users.index'));

        $created = User::where('email', 'developer-two@example.test')->firstOrFail();
        $this->assertTrue((bool) $created->is_developer);
        $this->assertTrue(FeatureFlags::isDeveloper($created));
    }

    public function test_only_developers_can_open_developer_feature_panel(): void
    {
        $admin = $this->user('plain-admin@example.test');
        $developer = $this->user('panel-dev@example.test', true);

        $this->actingAs($admin)->get('/admin/dev/features')->assertNotFound();
        $this->actingAs($developer)->get('/admin/dev/features')->assertOk();
    }

    private function user(string $email, bool $isDeveloper = false): User
    {
        return User::create([
            'name' => $isDeveloper ? 'Developer User' : 'Admin User',
            'email' => $email,
            'password' => Hash::make('password'),
            'is_developer' => $isDeveloper,
            'email_verified_at' => now(),
        ]);
    }
}
