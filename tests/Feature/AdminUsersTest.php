<?php

namespace Tests\Feature;

use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Filament\Resources\AdminUsers\AdminUserResource;
use App\Filament\Resources\AdminUsers\Pages\ManageAdminUsers;
use App\Models\AdminUser;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/** Admin passwords and the Admin users screen: profile password change, add/edit/switch off/reset/delete, safeguards, server reset. */
class AdminUsersTest extends TestCase
{
    use CreatesShopData;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }

        $this->setting('admin_locale', 'bn');
        app()->setLocale('bn');
    }

    private function admin(string $role = AdminUser::ROLE_SUPER_ADMIN, array $extra = []): AdminUser
    {
        static $n = 0;
        $n++;

        return AdminUser::create(array_merge(['username' => "{$role}{$n}", 'password' => 'old-password-1', 'role' => $role], $extra));
    }

    // ── 1. own password ─────────────────────────────────────────────────

    public function test_any_admin_can_change_their_own_password_with_the_old_one(): void
    {
        foreach ([AdminUser::ROLE_SUPER_ADMIN, AdminUser::ROLE_MANAGER, AdminUser::ROLE_ORDER_STAFF] as $role) {
            $me = $this->admin($role);
            $this->actingAs($me, 'admin');

            Livewire::test(EditProfile::class)
                ->fillForm(['currentPassword' => 'old-password-1', 'password' => 'brand-new-pass', 'passwordConfirmation' => 'brand-new-pass'])
                ->call('save')
                ->assertHasNoFormErrors();

            $this->assertTrue(Hash::check('brand-new-pass', $me->fresh()->password), $role);
        }
    }

    public function test_the_password_change_is_refused_when_old_is_wrong_or_new_is_short_or_they_differ(): void
    {
        $me = $this->admin();
        $this->actingAs($me, 'admin');

        Livewire::test(EditProfile::class)
            ->fillForm(['currentPassword' => 'WRONG-password', 'password' => 'brand-new-pass', 'passwordConfirmation' => 'brand-new-pass'])
            ->call('save')->assertHasFormErrors(['currentPassword']);

        Livewire::test(EditProfile::class)
            ->fillForm(['currentPassword' => 'old-password-1', 'password' => 'short7!', 'passwordConfirmation' => 'short7!'])
            ->call('save')->assertHasFormErrors(['password']);

        Livewire::test(EditProfile::class)
            ->fillForm(['currentPassword' => 'old-password-1', 'password' => 'brand-new-pass', 'passwordConfirmation' => 'something-else-1'])
            ->call('save')->assertHasFormErrors(['password']);

        Livewire::test(EditProfile::class)
            ->fillForm(['currentPassword' => '', 'password' => 'brand-new-pass', 'passwordConfirmation' => 'brand-new-pass'])
            ->call('save')->assertHasFormErrors(['currentPassword']);

        $this->assertTrue(Hash::check('old-password-1', $me->fresh()->password), 'nothing changed');
    }

    public function test_the_profile_page_opens_and_keeps_the_password_when_left_empty(): void
    {
        $me = $this->admin(AdminUser::ROLE_MANAGER);
        $this->actingAs($me, 'admin');

        $this->get('/admin/profile')->assertOk();

        Livewire::test(EditProfile::class)
            ->fillForm(['name' => 'Karim Sahib'])
            ->call('save')->assertHasNoFormErrors();

        // A new e-mail needs the password too.
        Livewire::test(EditProfile::class)
            ->fillForm(['email' => 'karim@example.com'])
            ->call('save')->assertHasFormErrors(['currentPassword']);
        Livewire::test(EditProfile::class)
            ->fillForm(['email' => 'karim@example.com', 'currentPassword' => 'old-password-1'])
            ->call('save')->assertHasNoFormErrors();

        $me = $me->fresh();
        $this->assertSame('Karim Sahib', $me->name);
        $this->assertSame('karim@example.com', $me->email);
        $this->assertTrue(Hash::check('old-password-1', $me->password));
    }

    // ── 2. the Admin users screen ───────────────────────────────────────

    public function test_a_super_admin_can_add_an_admin_with_name_email_password_and_role(): void
    {
        $this->actingAs($this->admin(), 'admin');

        Livewire::test(ManageAdminUsers::class)
            ->callAction(TestAction::make('create')->table(), ['username' => 'rahim', 'name' => 'Rahim Uddin', 'email' => 'rahim@example.com', 'password' => 'rahim-pass-1', 'role' => AdminUser::ROLE_MANAGER, 'is_active' => true])
            ->assertHasNoFormErrors();

        $rahim = AdminUser::where('username', 'rahim')->firstOrFail();
        $this->assertSame('Rahim Uddin', $rahim->name);
        $this->assertSame(AdminUser::ROLE_MANAGER, $rahim->role);
        $this->assertTrue($rahim->is_active);
        $this->assertNotSame('rahim-pass-1', $rahim->password);
        $this->assertTrue(Hash::check('rahim-pass-1', $rahim->password));
    }

    public function test_adding_an_admin_checks_the_password_username_and_email(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->admin(AdminUser::ROLE_MANAGER, ['username' => 'taken', 'email' => 'taken@example.com']);

        Livewire::test(ManageAdminUsers::class)
            ->callAction(TestAction::make('create')->table(), ['username' => 'new1', 'password' => 'short', 'role' => AdminUser::ROLE_MANAGER])
            ->assertHasFormErrors(['password']);
        Livewire::test(ManageAdminUsers::class)
            ->callAction(TestAction::make('create')->table(), ['username' => 'new1', 'role' => AdminUser::ROLE_MANAGER])
            ->assertHasFormErrors(['password' => 'required']);
        Livewire::test(ManageAdminUsers::class)
            ->callAction(TestAction::make('create')->table(), ['username' => 'taken', 'password' => 'long-enough-1', 'role' => AdminUser::ROLE_MANAGER])
            ->assertHasFormErrors(['username']);
        Livewire::test(ManageAdminUsers::class)
            ->callAction(TestAction::make('create')->table(), ['username' => 'new2', 'email' => 'taken@example.com', 'password' => 'long-enough-1', 'role' => AdminUser::ROLE_MANAGER])
            ->assertHasFormErrors(['email']);

        $this->assertNull(AdminUser::where('username', 'new1')->first());
    }

    public function test_a_super_admin_can_edit_an_admin_and_a_blank_password_keeps_the_old_one(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $staff = $this->admin(AdminUser::ROLE_ORDER_STAFF);

        Livewire::test(ManageAdminUsers::class)
            ->callAction(TestAction::make('edit')->table($staff), ['name' => 'Staff One', 'email' => 's1@example.com', 'role' => AdminUser::ROLE_MANAGER, 'password' => ''])
            ->assertHasNoFormErrors();

        $staff = $staff->fresh();
        $this->assertSame('Staff One', $staff->name);
        $this->assertSame(AdminUser::ROLE_MANAGER, $staff->role);
        $this->assertTrue(Hash::check('old-password-1', $staff->password));
    }

    public function test_a_super_admin_can_reset_another_admins_password(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $staff = $this->admin(AdminUser::ROLE_ORDER_STAFF, ['remember_token' => 'old-token']);

        Livewire::test(ManageAdminUsers::class)
            ->callAction(TestAction::make('resetPassword')->table($staff), ['password' => 'fresh-password-9', 'passwordConfirmation' => 'fresh-password-9'])
            ->assertHasNoFormErrors();

        $staff = $staff->fresh();
        $this->assertTrue(Hash::check('fresh-password-9', $staff->password));
        $this->assertNotSame('old-token', $staff->remember_token, 'remembered sign-ins stop working');

        Livewire::test(ManageAdminUsers::class)
            ->callAction(TestAction::make('resetPassword')->table($staff), ['password' => 'short', 'passwordConfirmation' => 'short'])
            ->assertHasFormErrors(['password']);
        Livewire::test(ManageAdminUsers::class)
            ->callAction(TestAction::make('resetPassword')->table($staff), ['password' => 'another-pass-1', 'passwordConfirmation' => 'different-pass-1'])
            ->assertHasFormErrors(['password']);
        $this->assertTrue(Hash::check('fresh-password-9', $staff->fresh()->password));
    }

    public function test_an_admin_can_be_switched_off_and_on_and_an_inactive_one_cannot_sign_in(): void
    {
        $boss = $this->admin(AdminUser::ROLE_SUPER_ADMIN, ['username' => 'boss']);
        $staff = $this->admin(AdminUser::ROLE_MANAGER, ['username' => 'staffer', 'email' => 'staffer@example.com']);
        $this->actingAs($boss, 'admin');

        Livewire::test(ManageAdminUsers::class)->callAction(TestAction::make('toggleActive')->table($staff));
        $this->assertFalse($staff->fresh()->is_active);

        // Cannot sign in (by username or by e-mail)...
        auth('admin')->logout();
        foreach (['staffer', 'staffer@example.com'] as $login) {
            Livewire::test(Login::class)
                ->fillForm(['username' => $login, 'password' => 'old-password-1'])
                ->call('authenticate')
                ->assertHasFormErrors(['username']);
            $this->assertGuest('admin');
        }

        // ...and one already signed in is turned away on the next request.
        $this->actingAs($staff->fresh(), 'admin')->get('/admin')->assertForbidden();

        $this->actingAs($boss, 'admin');
        Livewire::test(ManageAdminUsers::class)->callAction(TestAction::make('toggleActive')->table($staff->fresh()));
        $this->assertTrue($staff->fresh()->is_active);

        auth('admin')->logout();
        Livewire::test(Login::class)
            ->fillForm(['username' => 'staffer@example.com', 'password' => 'old-password-1'])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($staff->fresh(), 'admin');
    }

    public function test_a_super_admin_can_delete_another_admin_and_non_super_admins_cannot_open_the_screen(): void
    {
        $boss = $this->admin();
        $other = $this->admin(AdminUser::ROLE_MANAGER);

        $this->actingAs($boss, 'admin');
        Livewire::test(ManageAdminUsers::class)->callAction(TestAction::make('delete')->table($other));
        $this->assertNull(AdminUser::find($other->id));

        foreach ([AdminUser::ROLE_MANAGER, AdminUser::ROLE_ORDER_STAFF] as $role) {
            $this->flushSession();
            $this->actingAs($this->admin($role), 'admin');
            $this->get('/admin/admin-users')->assertForbidden();
        }
    }

    // ── 4. safeguards ───────────────────────────────────────────────────

    public function test_the_last_super_admin_cannot_be_deleted_deactivated_or_demoted(): void
    {
        $only = $this->admin();
        $manager = $this->admin(AdminUser::ROLE_MANAGER);

        foreach ([
            fn () => $only->delete(),
            fn () => $only->update(['is_active' => false]),
            fn () => $only->update(['role' => AdminUser::ROLE_MANAGER]),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('The last super admin must be protected.');
            } catch (\DomainException) {
                $this->assertTrue(true);
            }
        }
        $this->assertTrue($only->fresh()->isSuperAdmin());
        $this->assertTrue($only->fresh()->is_active);

        // A second super admin that is switched off does not count: the first is still the last active one.
        $second = $this->admin(AdminUser::ROLE_SUPER_ADMIN, ['is_active' => false]);
        $this->assertTrue($only->fresh()->isLastActiveSuperAdmin());
        $this->expectException(\DomainException::class);
        $only->fresh()->delete();
    }

    public function test_with_two_super_admins_one_can_go_but_never_the_last_one(): void
    {
        $a = $this->admin();
        $b = $this->admin();

        $a->update(['role' => AdminUser::ROLE_MANAGER]); // fine: b remains
        $this->assertTrue($b->fresh()->isLastActiveSuperAdmin());

        $this->expectException(\DomainException::class);
        $b->update(['is_active' => false]);
    }

    public function test_the_screen_hides_delete_and_off_for_yourself_and_the_last_super_admin(): void
    {
        $me = $this->admin();
        $manager = $this->admin(AdminUser::ROLE_MANAGER);
        $this->actingAs($me, 'admin');

        $page = Livewire::test(ManageAdminUsers::class);
        $page->assertActionHidden(TestAction::make('delete')->table($me));
        $page->assertActionHidden(TestAction::make('toggleActive')->table($me));
        $page->assertActionVisible(TestAction::make('delete')->table($manager));
        $page->assertActionVisible(TestAction::make('toggleActive')->table($manager));
        $this->assertFalse(AdminUserResource::canDelete($me));
        $this->assertTrue(AdminUserResource::canDelete($manager));

        // With a second super admin, you still cannot delete or switch off yourself — the other one can be removed.
        $other = $this->admin();
        $page = Livewire::test(ManageAdminUsers::class);
        $page->assertActionHidden(TestAction::make('delete')->table($me));
        $page->assertActionHidden(TestAction::make('toggleActive')->table($me));
        $page->assertActionVisible(TestAction::make('delete')->table($other));
    }

    public function test_your_own_role_and_active_switch_are_frozen_in_the_edit_form(): void
    {
        $me = $this->admin();
        $this->admin(); // a second super admin, so only "yourself" is the reason
        $this->actingAs($me, 'admin');

        Livewire::test(ManageAdminUsers::class)
            ->callAction(TestAction::make('edit')->table($me), ['role' => AdminUser::ROLE_ORDER_STAFF, 'is_active' => false, 'name' => 'Me'])
            ->assertHasNoFormErrors();

        $me = $me->fresh();
        $this->assertSame('Me', $me->name);
        $this->assertTrue($me->isSuperAdmin());
        $this->assertTrue($me->is_active);
    }

    // ── 5. server reset ─────────────────────────────────────────────────

    public function test_the_server_command_resets_a_password_and_switches_the_account_on(): void
    {
        $boss = $this->admin(AdminUser::ROLE_SUPER_ADMIN, ['username' => 'boss', 'email' => 'boss@example.com']);
        $boss->forceFill(['is_active' => false, 'remember_token' => 'old'])->saveQuietly();

        $this->artisan('admin:reset-password', ['user' => 'boss'])
            ->expectsQuestion('New password (at least 8 characters)', 'server-reset-1')
            ->expectsQuestion('New password again', 'server-reset-1')
            ->assertSuccessful();

        $boss = $boss->fresh();
        $this->assertTrue(Hash::check('server-reset-1', $boss->password));
        $this->assertTrue($boss->is_active);
        $this->assertNotSame('old', $boss->remember_token);

        // By e-mail, and with the password given as an option.
        $this->artisan('admin:reset-password', ['user' => 'boss@example.com', '--password' => 'option-pass-22'])->assertSuccessful();
        $this->assertTrue(Hash::check('option-pass-22', $boss->fresh()->password));
    }

    public function test_the_server_command_refuses_short_or_mismatched_passwords_and_unknown_users(): void
    {
        $boss = $this->admin(AdminUser::ROLE_SUPER_ADMIN, ['username' => 'boss']);

        $this->artisan('admin:reset-password', ['user' => 'boss', '--password' => 'short'])->assertFailed();

        $this->artisan('admin:reset-password', ['user' => 'boss'])
            ->expectsQuestion('New password (at least 8 characters)', 'one-password-1')
            ->expectsQuestion('New password again', 'two-password-2')
            ->assertFailed();

        $this->artisan('admin:reset-password', ['user' => 'nobody', '--password' => 'long-enough-1'])->assertFailed();

        $this->assertTrue(Hash::check('old-password-1', $boss->fresh()->password), 'nothing changed');
    }

    public function test_with_a_single_admin_the_command_needs_no_name(): void
    {
        $boss = $this->admin(AdminUser::ROLE_SUPER_ADMIN, ['username' => 'onlyone']);

        $this->artisan('admin:reset-password', ['--password' => 'long-enough-1'])->assertSuccessful();

        $this->assertTrue(Hash::check('long-enough-1', $boss->fresh()->password));
    }
}
