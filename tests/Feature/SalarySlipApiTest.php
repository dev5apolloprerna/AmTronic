<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\SalarySlip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalarySlipApiTest extends TestCase
{
    use RefreshDatabase;

    private function employee(string $name = 'App Employee', bool $apiOnly = true): User
    {
        $designation = Designation::firstOrCreate(
            ['name' => $apiOnly ? 'Sales Executive' : 'Sales'],
            ['status' => 'active', 'can_login' => true, 'api_only' => $apiOnly],
        );

        return User::factory()->create([
            'name' => $name, 'designation_id' => $designation->id, 'role' => 'user', 'status' => 'active', 'monthly_salary' => 30000,
        ]);
    }

    private function token(User $user): string
    {
        $token = Str::random(60);
        $user->forceFill(['api_token' => hash('sha256', $token)])->save();

        return $token;
    }

    private function slip(User $employee, int $month, string $status = SalarySlip::PROCESSED, array $extra = []): SalarySlip
    {
        return SalarySlip::create($extra + [
            'employee_id' => $employee->id, 'year' => 2026, 'month' => $month, 'days_in_month' => 30,
            'monthly_salary' => 30000, 'per_day' => 1000, 'full_days' => 24, 'half_days' => 1, 'absent_days' => 3,
            'paid_leave' => 2, 'unpaid_leave' => 1.5, 'payable_days' => 28.5, 'leave_deduction' => 1500,
            'deduction' => 500, 'deduction_reason' => 'Advance recovery', 'incentive' => 2000, 'net_salary' => 30000,
            'status' => $status, 'processed_at' => $status === SalarySlip::PROCESSED ? now() : null,
        ]);
    }

    public function test_list_returns_only_own_processed_slips(): void
    {
        $me = $this->employee();
        $other = $this->employee('Other');
        $this->slip($me, 8);
        $this->slip($me, 9);
        $this->slip($me, 10, SalarySlip::SUBMITTED); // draft - hidden
        $this->slip($other, 9);                       // someone else - hidden

        $response = $this->withToken($this->token($me))->postJson('/api/salary-slips/list')
            ->assertOk()->assertJsonPath('status', true)->assertJsonCount(2, 'data');

        $this->assertSame(['September 2026', 'August 2026'], array_column($response->json('data'), 'period'));
        $this->assertSame(30000.0, (float) $response->json('data.0.net_salary'));
        $this->assertSame(2000.0, (float) $response->json('data.0.total_deductions'));
        $this->assertStringContainsString('/api/salary-slips/', $response->json('data.0.pdf_url'));
        $this->assertSame([2026], $response->json('years'));
    }

    public function test_detail_gives_attendance_earnings_and_deductions(): void
    {
        $me = $this->employee('Ravi Patel');
        $slip = $this->slip($me, 9);

        $this->withToken($this->token($me))->postJson("/api/salary-slips/{$slip->id}/show")
            ->assertOk()
            ->assertJsonPath('data.employee.name', 'Ravi Patel')
            ->assertJsonPath('data.attendance.full_days', 24)
            ->assertJsonPath('data.attendance.half_days', 1)
            ->assertJsonPath('data.attendance.absent_days', 3)
            ->assertJsonPath('data.deductions.1.note', 'Advance recovery')
            ->assertJsonPath('data.net_salary_in_words', fn ($words) => str_contains(strtolower($words), 'thirty thousand'));
    }

    public function test_cannot_see_others_or_draft_slips(): void
    {
        $me = $this->employee();
        $token = $this->token($me);
        $others = $this->slip($this->employee('Other'), 9);
        $draft = $this->slip($me, 10, SalarySlip::SUBMITTED);

        $this->withToken($token)->postJson("/api/salary-slips/{$others->id}/show")->assertNotFound()->assertJsonPath('status', false);
        $this->withToken($token)->postJson("/api/salary-slips/{$draft->id}/show")->assertNotFound();
        $this->withToken($token)->postJson("/api/salary-slips/{$others->id}/pdf")->assertNotFound()->assertJsonPath('status', false);
    }
        public function test_salary_pdf_and_download_urls_preserve_the_configured_subdirectory(): void
    {
        config(['app.url' => 'https://getdemo.in/AmTronic/']);
        $employee = $this->employee();
        $slip = $this->slip($employee, 9);
        $token = $this->token($employee);

        $list = $this->withToken($token)->postJson('/api/salary-slips/list')->assertOk();
        $detail = $this->withToken($token)->postJson("/api/salary-slips/{$slip->id}/show")->assertOk();
        foreach (['pdf_url', 'download_url'] as $field) {
            $url = $list->json('data.0.'.$field);
            $this->assertStringStartsWith('https://getdemo.in/AmTronic/api/salary-slips/'.$slip->id.'/pdf?', $url);
            $this->assertSame($url, $detail->json('data.'.$field));
        }

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $server = [
            'SCRIPT_NAME' => '/AmTronic/index.php',
            'PHP_SELF' => '/AmTronic/index.php',
            'SCRIPT_FILENAME' => '/var/www/AmTronic/index.php',
        ];
        foreach (['pdf_url' => 'inline;', 'download_url' => 'attachment;'] as $field => $disposition) {
            $url = $list->json('data.0.'.$field);
            $response = $this->call('GET', $url, [], [], [], $server)
                ->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith($disposition, $response->headers->get('Content-Disposition'));
            $this->assertStringStartsWith('%PDF-', $response->getContent());
            $this->call('GET', str_replace('signature=', 'signature=x', $url), [], [], [], $server)
                ->assertUnauthorized();
        }
    }


    public function test_pdf_downloads_with_token_and_with_signed_link(): void
    {
        $me = $this->employee('Ravi Patel');
        $slip = $this->slip($me, 9);
        $token = $this->token($me);

        $pdf = $this->withToken($token)->get("/api/salary-slips/{$slip->id}/pdf")->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringContainsString('salary-slip-ravi-patel-2026-09.pdf', $pdf->headers->get('Content-Disposition'));

        // the app calls every endpoint with POST
        $this->withToken($token)->post("/api/salary-slips/{$slip->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $list = $this->withToken($token)->postJson('/api/salary-slips/list');
        $url = $list->json('data.0.pdf_url');
        $downloadUrl = $list->json('data.0.download_url');
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        // pdf_url opens inline (viewer / WebView); download_url saves the file
        $inline = $this->get($url)->assertOk();
        $this->assertStringStartsWith('inline;', $inline->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $inline->getContent());
        $this->assertLessThan(200 * 1024, strlen($inline->getContent()), 'fonts must be subset to keep the PDF small');
        $this->assertStringStartsWith('attachment;', $this->get($downloadUrl)->assertOk()->headers->get('Content-Disposition'));

        // tampered signed link is rejected
        $this->get(str_replace('signature=', 'signature=x', $url))->assertUnauthorized();
    }


    public function test_requires_login(): void
    {
        $this->postJson('/api/salary-slips/list')->assertUnauthorized();
    }

    public function test_employee_web_page_shows_own_processed_slips(): void
    {
        $me = $this->employee('Web Person', false);
        $mine = $this->slip($me, 9);
        $draft = $this->slip($me, 10, SalarySlip::SUBMITTED);
        $others = $this->slip($this->employee('Other', false), 9);

        $this->actingAs($me)->get(route('my-salary-slips.index'))
            ->assertOk()->assertSee('September 2026')->assertDontSee('October 2026');

        $this->get(route('my-salary-slips.show', $mine->id))->assertOk()->assertSee('Web Person');
        $this->get(route('my-salary-slips.download', $mine->id))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('my-salary-slips.show', $draft->id))->assertNotFound();
        $this->get(route('my-salary-slips.show', $others->id))->assertNotFound();
    }
}
