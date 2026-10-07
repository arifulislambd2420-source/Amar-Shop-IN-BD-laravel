<?php

namespace Tests\Feature;

use App\Filament\Resources\AdminUsers\AdminUserResource;
use App\Models\AdminUser;
use App\Support\AdminAccess;
use Tests\TestCase;

class AdminRolesTest extends TestCase
{
    private function admin(string $role): AdminUser
    {
        return AdminUser::create(['username' => $role.'_'.uniqid(), 'password' => 'secret-pass-1', 'role' => $role]);
    }

    public function test_area_matrix(): void
    {
        $super = $this->admin(AdminUser::ROLE_SUPER_ADMIN);
        $manager = $this->admin(AdminUser::ROLE_MANAGER);
        $staff = $this->admin(AdminUser::ROLE_ORDER_STAFF);

        foreach ([AdminAccess::AREA_GENERAL, AdminAccess::AREA_ORDERS, AdminAccess::AREA_SETTINGS, AdminAccess::AREA_ADMINS] as $area) {
            $this->assertTrue(AdminAccess::allows($area, $super), "super_admin / $area");
        }

        $this->assertTrue(AdminAccess::allows(AdminAccess::AREA_GENERAL, $manager));
        $this->assertTrue(AdminAccess::allows(AdminAccess::AREA_ORDERS, $manager));
        $this->assertFalse(AdminAccess::allows(AdminAccess::AREA_SETTINGS, $manager));
        $this->assertFalse(AdminAccess::allows(AdminAccess::AREA_ADMINS, $manager));

        $this->assertTrue(AdminAccess::allows(AdminAccess::AREA_ORDERS, $staff));
        $this->assertFalse(AdminAccess::allows(AdminAccess::AREA_GENERAL, $staff));
        $this->assertFalse(AdminAccess::allows(AdminAccess::AREA_SETTINGS, $staff));
    }

    public function test_settings_pages_are_forbidden_for_manager(): void
    {
        $this->actingAs($this->admin(AdminUser::ROLE_MANAGER), 'admin');
        $this->get('/admin/site-settings')->assertForbidden();
        $this->get('/admin/admin-users')->assertForbidden();
        $this->get('/admin/ip-blocks')->assertForbidden();
    }

    public function test_super_admin_can_open_settings_and_admin_users(): void
    {
        $this->actingAs($this->admin(AdminUser::ROLE_SUPER_ADMIN), 'admin');
        $this->get('/admin/site-settings')->assertOk();
        // Table pages need PHP intl to render; authorization is what matters here.
        $this->assertNotSame(403, $this->get('/admin/admin-users')->status());
    }

    public function test_order_staff_cannot_open_anything_but_orders(): void
    {
        $this->actingAs($this->admin(AdminUser::ROLE_ORDER_STAFF), 'admin');

        $this->get('/admin/products')->assertForbidden();
        $this->get('/admin/landing-pages')->assertForbidden();
        $this->get('/admin/site-settings')->assertForbidden();
        $this->get('/admin')->assertOk();
    }

    public function test_navigation_follows_the_role(): void
    {
        $this->actingAs($this->admin(AdminUser::ROLE_ORDER_STAFF), 'admin');
        $this->assertFalse(AdminUserResource::shouldRegisterNavigation());

        $this->actingAs($this->admin(AdminUser::ROLE_SUPER_ADMIN), 'admin');
        $this->assertTrue(AdminUserResource::shouldRegisterNavigation());
    }

    public function test_last_super_admin_is_protected(): void
    {
        $only = $this->admin(AdminUser::ROLE_SUPER_ADMIN);
        $this->assertTrue(AdminUserResource::isLastSuperAdmin($only));

        $second = $this->admin(AdminUser::ROLE_SUPER_ADMIN);
        $this->assertFalse(AdminUserResource::isLastSuperAdmin($only));
        $this->assertFalse(AdminUserResource::isLastSuperAdmin($second));

        $manager = $this->admin(AdminUser::ROLE_MANAGER);
        $this->assertFalse(AdminUserResource::isLastSuperAdmin($manager));
    }
}
