<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Only Super Admins and employees whose designation is flagged can_login
 * (e.g. "Sales") may log in. Everyone else is a record only.
 */
class EmployeeLoginRuleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attrs = []): User
    {
        return User::factory()->create($attrs + ['role' => 'super_admin', 'status' => 'active']);
    }

    private function designation(string $name, bool $canLogin): Designation
    {
        return Designation::create(['name' => $name, 'status' => 'active', 'can_login' => $canLogin]);
    }

    /** An employee with the factory password "password". */
    private function employee(?Designation $designation, array $attrs = []): User
    {
        return User::factory()->create($attrs + [
            'role' => 'user',
            'status' => 'active',
            'designation_id' => $designation?->id,
        ]);
    }

    private function logIn(User $user, string $password = 'password')
    {
        return $this->post(route('login.attempt'), ['email' => $user->email, 'password' => $password]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ravi Patel',
            'email' => 'ravi@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'user',
            'status' => 'active',
        ], $overrides);
    }

    /** The <tr> of a list whose name cell is exactly $name ("Sales" must not match "Field Sales"). */
    private function row(string $html, string $name): string
    {
        $this->assertSame(1, preg_match('/<tr>(?:(?!<\/tr>).)*<td>' . preg_quote($name, '/') . '<\/td>(?:(?!<\/tr>).)*<\/tr>/s', $html, $m), "No row for $name");

        return $m[0];
    }

    // ------------------------------------------------------------------ login gate

    public function test_employee_with_a_login_designation_can_log_in(): void
    {
        $sales = $this->employee($this->designation('Sales', true));

        $this->logIn($sales)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($sales);
    }

    public function test_employee_with_a_non_login_designation_cannot_log_in_even_with_a_valid_password(): void
    {
        $technician = $this->employee($this->designation('Technician', false));

        $this->logIn($technician)->assertSessionHasErrors(['email' => 'Your account is not enabled for login. Please contact the administrator.']);
        $this->assertGuest();
    }

    public function test_employee_without_a_designation_cannot_log_in(): void
    {
        $this->logIn($this->employee(null))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_super_admin_can_always_log_in_regardless_of_designation(): void
    {
        $noDesignation = $this->admin();
        $withNonLogin = $this->admin(['designation_id' => $this->designation('Technician', false)->id]);

        $this->logIn($noDesignation)->assertRedirect(route('dashboard'));
        $this->post(route('logout'));

        $this->logIn($withNonLogin)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($withNonLogin);
    }

    public function test_inactive_employee_still_gets_the_inactive_message(): void
    {
        $sales = $this->employee($this->designation('Sales', true), ['status' => 'inactive']);

        $this->logIn($sales)->assertSessionHasErrors(['email' => 'Your account is inactive. Please contact the administrator.']);
        $this->assertGuest();
    }

    public function test_unticking_can_login_on_a_designation_locks_out_its_employees(): void
    {
        $designation = $this->designation('Sales', true);
        $sales = $this->employee($designation);

        $this->logIn($sales)->assertRedirect(route('dashboard'));
        $this->post(route('logout'));

        $this->actingAs($this->admin())->put(route('designations.update', $designation), ['name' => 'Sales', 'status' => 'active']);
        $this->post(route('logout'));

        $this->assertFalse($designation->fresh()->can_login);
        $this->logIn($sales)->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_an_account_with_no_password_can_never_log_in(): void
    {
        $sales = $this->employee($this->designation('Sales', true), ['password' => null]);

        $this->logIn($sales, 'anything')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_can_login_is_reported_by_the_model(): void
    {
        $sales = $this->designation('Sales', true);
        $tech = $this->designation('Technician', false);

        $this->assertTrue($this->employee($sales)->canLogin());
        $this->assertFalse($this->employee($tech)->canLogin());
        $this->assertFalse($this->employee(null)->canLogin());
        $this->assertFalse($this->employee($sales, ['status' => 'inactive'])->canLogin());
        $this->assertTrue($this->admin()->canLogin());
        $this->assertFalse($this->admin(['status' => 'inactive'])->canLogin());
    }

    // ------------------------------------------------------------------ employee form: create

    public function test_a_login_employee_needs_an_email_and_a_password(): void
    {
        $sales = $this->designation('Sales', true);

        $this->actingAs($this->admin())
            ->post(route('users.store'), ['email' => '', 'password' => '', 'password_confirmation' => '', 'designation_id' => $sales->id] + $this->payload())
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertDatabaseMissing('users', ['name' => 'Ravi Patel']);
    }

    public function test_a_login_employee_is_saved_with_a_hashed_password_that_works(): void
    {
        $sales = $this->designation('Sales', true);

        $this->actingAs($this->admin())->post(route('users.store'), $this->payload(['designation_id' => $sales->id]))
            ->assertSessionHasNoErrors();
        $this->post(route('logout'));

        $saved = User::where('email', 'ravi@example.com')->firstOrFail();
        $this->assertNotSame('secret123', $saved->password);
        $this->assertTrue(Hash::check('secret123', $saved->password));
        $this->logIn($saved, 'secret123')->assertRedirect(route('dashboard'));
    }

    public function test_a_non_login_employee_needs_no_email_or_password(): void
    {
        $technician = $this->designation('Technician', false);
        $admin = $this->admin();
        $bare = ['name' => 'Tech One', 'role' => 'user', 'status' => 'active', 'designation_id' => $technician->id];

        $this->actingAs($admin)->post(route('users.store'), $bare)->assertSessionHasNoErrors();
        // several employees may have no email: NULLs do not collide on the unique index
        $this->actingAs($admin)->post(route('users.store'), ['name' => 'Tech Two'] + $bare)->assertSessionHasNoErrors();

        $one = User::where('name', 'Tech One')->firstOrFail();
        $this->assertNull($one->email);
        $this->assertNull($one->password);
        $this->assertDatabaseHas('users', ['name' => 'Tech Two', 'email' => null, 'password' => null]);
    }

    public function test_an_employee_with_no_designation_needs_no_credentials(): void
    {
        $this->actingAs($this->admin())
            ->post(route('users.store'), ['name' => 'Helper', 'role' => 'user', 'status' => 'active'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['name' => 'Helper', 'email' => null, 'password' => null]);
    }

    public function test_a_super_admin_always_needs_credentials(): void
    {
        $this->actingAs($this->admin())
            ->post(route('users.store'), ['name' => 'Second Admin', 'role' => 'super_admin', 'status' => 'active'])
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_a_password_typed_for_a_non_login_employee_is_not_stored(): void
    {
        $technician = $this->designation('Technician', false);

        $this->actingAs($this->admin())
            ->post(route('users.store'), $this->payload(['designation_id' => $technician->id]))
            ->assertSessionHasNoErrors();

        $saved = User::where('email', 'ravi@example.com')->firstOrFail(); // the email is still kept as contact info
        $this->assertNull($saved->password);
        $this->assertFalse($saved->canLogin());
    }

    public function test_an_email_given_to_a_non_login_employee_must_still_be_unique(): void
    {
        $technician = $this->designation('Technician', false);
        $this->employee($technician, ['email' => 'taken@example.com']);

        $this->actingAs($this->admin())
            ->post(route('users.store'), ['name' => 'Tech Two', 'email' => 'taken@example.com', 'role' => 'user', 'status' => 'active', 'designation_id' => $technician->id])
            ->assertSessionHasErrors('email');
    }

    // ------------------------------------------------------------------ employee form: edit

    public function test_promoting_a_password_less_employee_to_a_login_designation_requires_a_password(): void
    {
        $sales = $this->designation('Sales', true);
        $tech = $this->designation('Technician', false);
        $employee = $this->employee($tech, ['password' => null, 'email' => 'tech@example.com', 'name' => 'Tech One']);
        $admin = $this->admin();
        $base = ['name' => 'Tech One', 'email' => 'tech@example.com', 'role' => 'user', 'status' => 'active', 'designation_id' => $sales->id];

        $this->actingAs($admin)->put(route('users.update', $employee), $base)
            ->assertSessionHasErrors('password');
        $this->assertSame($tech->id, $employee->fresh()->designation_id);

        $this->actingAs($admin)->put(route('users.update', $employee), $base + ['password' => 'newsecret1', 'password_confirmation' => 'newsecret1'])
            ->assertSessionHasNoErrors();
        $this->post(route('logout'));

        $this->assertTrue($employee->fresh()->canLogin());
        $this->logIn($employee->fresh(), 'newsecret1')->assertRedirect(route('dashboard'));
    }

    public function test_promoting_also_needs_an_email(): void
    {
        $sales = $this->designation('Sales', true);
        $employee = $this->employee($this->designation('Technician', false), ['password' => null, 'email' => null]);

        $this->actingAs($this->admin())
            ->put(route('users.update', $employee), ['name' => $employee->name, 'role' => 'user', 'status' => 'active', 'designation_id' => $sales->id, 'password' => 'newsecret1', 'password_confirmation' => 'newsecret1'])
            ->assertSessionHasErrors('email');
    }

    public function test_editing_a_login_employee_without_a_new_password_keeps_the_old_one(): void
    {
        $sales = $this->designation('Sales', true);
        $employee = $this->employee($sales, ['name' => 'Old Name']);
        $before = $employee->password;

        $this->actingAs($this->admin())
            ->put(route('users.update', $employee), ['name' => 'New Name', 'email' => $employee->email, 'role' => 'user', 'status' => 'active', 'designation_id' => $sales->id])
            ->assertSessionHasNoErrors();

        $this->assertSame('New Name', $employee->fresh()->name);
        $this->assertSame($before, $employee->fresh()->password);
    }

    public function test_demoting_to_a_non_login_designation_keeps_the_stored_password_but_blocks_login(): void
    {
        $sales = $this->designation('Sales', true);
        $tech = $this->designation('Technician', false);
        $employee = $this->employee($sales);
        $before = $employee->password;

        $this->actingAs($this->admin())
            ->put(route('users.update', $employee), ['name' => $employee->name, 'email' => $employee->email, 'role' => 'user', 'status' => 'active', 'designation_id' => $tech->id])
            ->assertSessionHasNoErrors();
        $this->post(route('logout'));

        $this->assertSame($before, $employee->fresh()->password);
        $this->logIn($employee->fresh())->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_password_typed_while_editing_a_non_login_employee_is_ignored(): void
    {
        $employee = $this->employee($this->designation('Technician', false), ['password' => null, 'email' => null]);

        $this->actingAs($this->admin())
            ->put(route('users.update', $employee), ['name' => $employee->name, 'role' => 'user', 'status' => 'active', 'designation_id' => $employee->designation_id, 'password' => 'sneaky123', 'password_confirmation' => 'sneaky123'])
            ->assertSessionHasNoErrors();

        $this->assertNull($employee->fresh()->password);
    }

    public function test_an_admin_cannot_edit_their_own_account_into_one_that_cannot_log_in(): void
    {
        $admin = $this->admin(['name' => 'Boss']);
        $base = ['name' => 'Boss', 'email' => $admin->email, 'status' => 'active'];

        $this->actingAs($admin)->put(route('users.update', $admin), $base + ['role' => 'user'])
            ->assertSessionHas('error', 'You cannot change your own account so that it can no longer log in.');
        $this->assertSame('super_admin', $admin->fresh()->role);

        // ...but editing themselves while staying an admin is fine
        $this->actingAs($admin)->put(route('users.update', $admin), ['name' => 'Boss Renamed', 'role' => 'super_admin'] + $base)
            ->assertSessionHasNoErrors();
        $this->assertSame('Boss Renamed', $admin->fresh()->name);
    }

    // ------------------------------------------------------------------ screens

    public function test_employee_form_tells_the_browser_which_designations_can_log_in(): void
    {
        $this->designation('Sales', true);
        $this->designation('Technician', false);

        $html = $this->actingAs($this->admin())->get(route('users.create'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-can-login="1"[^>]*>Sales</', $html);
        $this->assertMatchesRegularExpression('/data-can-login="0"[^>]*>Technician</', $html);
        $this->assertStringContainsString('data-employee-form', $html);
        $this->assertStringContainsString('data-login-only', $html);
    }

    public function test_edit_form_says_when_an_employee_has_no_password_yet(): void
    {
        $admin = $this->admin();
        $none = $this->employee($this->designation('Technician', false), ['password' => null]);
        $has = $this->employee($this->designation('Sales', true));

        $noneHtml = $this->actingAs($admin)->get(route('users.edit', $none))->assertOk()->getContent();
        $hasHtml = $this->actingAs($admin)->get(route('users.edit', $has))->assertOk()->getContent();

        $this->assertStringContainsString('data-password-required="1"', $noneHtml);
        $this->assertStringContainsString('no password yet', $noneHtml);
        $this->assertStringContainsString('data-password-required="0"', $hasHtml);
        $this->assertStringContainsString('Leave blank to keep current password.', $hasHtml);
    }

    public function test_employee_list_shows_who_can_log_in_and_a_dash_for_a_missing_email(): void
    {
        $this->employee($this->designation('Sales', true), ['name' => 'Sam Sales']);
        $this->employee($this->designation('Technician', false), ['name' => 'Tina Tech', 'email' => null]);

        $html = $this->actingAs($this->admin())->get(route('users.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Can Log In', $html);
        $this->assertStringContainsString('<td>Yes</td>', $this->row($html, 'Sam Sales'));
        $tina = $this->row($html, 'Tina Tech');
        $this->assertStringContainsString('<td>No</td>', $tina);
        $this->assertStringContainsString('<td>-</td>', $tina);
    }

    // ------------------------------------------------------------------ designation flag

    public function test_designation_can_login_flag_is_saved_and_shown(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('designations.store'), ['name' => 'Sales', 'status' => 'active', 'can_login' => '0'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('designations.store'), ['name' => 'Field Sales', 'status' => 'active', 'can_login' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertFalse(Designation::where('name', 'Sales')->firstOrFail()->can_login, 'defaults to no login');

        $sales = Designation::where('name', 'Sales')->firstOrFail();

        // a ticked checkbox submits the hidden 0 followed by the 1
        $this->actingAs($admin)->put(route('designations.update', $sales), ['name' => 'Sales', 'status' => 'active', 'can_login' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($sales->fresh()->can_login);

        $html = $this->actingAs($admin)->get(route('designations.index'))->getContent();
        $this->assertStringContainsString('Can Log In', $html);
        $this->assertStringContainsString('<td>Yes</td>', $this->row($html, 'Sales'));
        $this->assertStringContainsString('<td>No</td>', $this->row($html, 'Field Sales'));

        $this->actingAs($admin)->get(route('designations.edit', $sales))->assertOk()->assertSee('checked', false);
    }

    // ------------------------------------------------------------------ fresh install

    public function test_seeded_sample_user_can_log_in_on_a_fresh_install(): void
    {
        $this->seed(DatabaseSeeder::class);

        $sample = User::where('email', 'user@example.com')->firstOrFail();
        $this->assertSame('Sales', $sample->designation->name);
        $this->assertTrue($sample->designation->can_login);

        $this->logIn($sample)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($sample);
    }
}
