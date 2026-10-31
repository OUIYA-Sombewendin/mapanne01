<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_moderator_only_sees_what_his_role_allows(): void
    {
        $moderator = User::factory()->admin('moderateur')->create();

        $this->actingAs($moderator)->get('/admin')->assertOk()
            ->assertSee('Signalements')->assertDontSee('Rôles et permissions')->assertDontSee('Sauvegardes');
        $this->get('/admin/avis')->assertOk();
        $this->get('/admin/prestataires')->assertOk();
        $this->get('/admin/reglages')->assertForbidden();
        $this->get('/admin/sauvegardes')->assertForbidden();
        $this->get('/admin/roles')->assertForbidden();
        $this->get('/admin/abonnements')->assertForbidden();
        $this->get('/admin/donnees/export/utilisateurs')->assertForbidden();
    }

    public function test_appearance_permission_opens_only_appearance_settings(): void
    {
        $role = Role::create(['slug' => 'design', 'name' => 'Design', 'permissions' => ['appearance.manage']]);
        $designer = User::factory()->admin('design')->create();

        $this->actingAs($designer)->get('/admin/reglages')->assertOk()->assertSee('Apparence et couleurs')->assertDontSee('Serveur d\'envoi');
        $this->get('/admin/reglages/mail')->assertForbidden();
        $this->put('/admin/reglages/appearance', ['theme_brand_color' => '#16a34a', 'theme_ink_color' => '#18223d', 'theme_header_style' => 'dark', 'theme_footer_style' => 'dark'])->assertSessionHasNoErrors();
        $this->assertSame('#16a34a', setting('theme_brand_color'));
        $this->assertNotNull($role);
    }

    public function test_admin_creates_role_with_permissions(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post('/admin/roles', [
            'name' => 'Comptable', 'permissions' => ['subscriptions.manage', 'data.export', 'inexistante'],
        ])->assertSessionHasErrors('permissions.2');

        $this->post('/admin/roles', ['name' => 'Comptable', 'permissions' => ['subscriptions.manage', 'data.export']])->assertRedirect('/admin/roles');
        $role = Role::where('name', 'Comptable')->first();
        $this->assertTrue($role->allows('data.export'));
        $this->assertFalse($role->allows('settings.manage'));
    }

    public function test_super_admin_role_is_protected_and_last_super_admin_kept(): void
    {
        $super = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $system = Role::superAdmin();

        // Renommable, mais garde toujours toutes les permissions.
        $this->actingAs($super)->put("/admin/roles/{$system->id}", ['name' => 'Administrateur', 'color' => 'gray', 'permissions' => []])->assertRedirect('/admin/roles');
        $this->assertSame('Administrateur', $system->fresh()->name);
        $this->assertTrue($system->fresh()->isSuperAdmin());
        $this->delete("/admin/roles/{$system->id}")->assertForbidden();

        // Retirer un super-admin est possible tant qu'il en reste un autre.
        $this->post("/admin/utilisateurs/{$other->id}/role", ['admin_role_id' => null])->assertRedirect();
        $this->assertFalse($other->fresh()->isAdmin());

        // Un modérateur ne peut pas nommer de super-admin.
        $moderator = User::factory()->admin('moderateur')->create();
        $moderator->adminRole->update(['permissions' => array_merge($moderator->adminRole->permissions, ['roles.manage'])]);
        $client = User::factory()->create();
        $this->actingAs($moderator)->post("/admin/utilisateurs/{$client->id}/role", ['admin_role_id' => $system->id])->assertForbidden();
    }

    public function test_admin_without_role_has_no_permission(): void
    {
        $admin = User::factory()->admin()->create(['admin_role_id' => null]);

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->get('/admin/prestataires')->assertForbidden();
        $this->assertFalse($admin->hasPermission('providers.view'));
    }

    public function test_roles_page_lists_counts_and_sorts(): void
    {
        $member = User::factory()->admin('support')->create();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/roles?sort=users&dir=desc')->assertOk()
            ->assertSee('Toutes')->assertSee('/ '.count(Permissions::all()));
        $support = Role::where('slug', 'support')->first();
        $this->get('/admin/utilisateurs?admin_role='.$support->id)->assertOk()->assertSee($member->email);
        $this->get('/admin/roles/nouveau')->assertOk()->assertSee('Tout accorder');

        $this->post('/admin/roles', ['name' => 'Caissier', 'color' => 'teal', 'permissions' => ['data.export']])->assertRedirect('/admin/roles');
        $this->assertSame('teal', Role::where('name', 'Caissier')->value('color'));
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        User::factory()->admin('support')->create();
        $support = Role::where('slug', 'support')->first();

        $this->actingAs(User::factory()->admin()->create())->delete("/admin/roles/{$support->id}")->assertSessionHasErrors('role');
        $this->assertNotNull($support->fresh());
    }
}
