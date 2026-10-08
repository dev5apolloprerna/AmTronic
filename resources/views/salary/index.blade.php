@extends('layouts.app')
@section('title', 'Salary Calculation')
@php($d = fn ($v) => \App\Support\SalaryCalculator::days($v))
@section('content')
<div class="card">
    <div class="card-header">
        <h3>Salary Calculation &mdash; {{ $periodLabel }}</h3>
        @php($locked = $monthStatus === 'processed')
        @if($locked)
            <span class="pill pill-approved">Processed &amp; locked</span>
        @elseif($monthStatus === 'submitted')
            <span class="pill pill-sent">Submitted &middot; editable</span>
        @else
            <span class="pill pill-draft">Not submitted</span>
        @endif
    </div>
    <div class="card-body">
        <form method="GET" class="filters-bar">
            <div class="form-group">
                <label for="month">Month</label>
                <select id="month" name="month" class="form-control">
                    @foreach(range(1, 12) as $m)
                        <option value="{{ $m }}" {{ $month === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="year">Year</label>
                <select id="year" name="year" class="form-control">
                    @foreach(range(now()->year + 1, now()->year - 5) as $y)
                        <option value="{{ $y }}" {{ $year === $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-secondary">Search</button>
            <a class="btn btn-secondary" href="{{ route('reports.salary') }}">Current Month</a>
        </form>

        <p class="text-muted salary-note">
            {{ $daysInMonth }} days in month &middot; Leave = Absent + &frac12; per Half Day &middot; first {{ \App\Support\SalaryCalculator::PAID_LEAVES_PER_MONTH }} leave days paid, extra leave cut at salary &divide; {{ $daysInMonth }} per day &middot;
            Net = Salary &minus; Leave Cut &minus; Deduction + Incentive
        </p>

        <div class="salary-steps">
            <div class="step {{ $monthStatus === 'pending' ? 'current' : 'done' }}"><b>1</b> Enter deduction / incentive</div>
            <div class="step {{ $monthStatus === 'submitted' ? 'current' : ($locked ? 'done' : '') }}"><b>2</b> Submit &mdash; edit or delete rows</div>
            <div class="step {{ $locked ? 'current done' : '' }}"><b>3</b> Process &mdash; final &amp; locked</div>
        </div>

        @if($locked)
            <div class="salary-banner banner-locked">
                <strong>Salary for {{ $periodLabel }} is processed and locked</strong>
                @if($processedSlip?->processed_at) on {{ $processedSlip->processed_at->format('d M Y, h:i A') }}@endif
                @if($processedSlip?->processedBy) by {{ $processedSlip->processedBy->name }}@endif.
                Amounts and attendance for this month can't be changed. If something is wrong, use <em>Delete &amp; Regenerate</em> below.
            </div>
        @elseif($monthStatus === 'submitted')
            <div class="salary-banner banner-submitted">
                <strong>Submitted, not final.</strong> Change any value and press <em>Update Submitted Salary</em>, or <em>Delete</em> a single row to clear it.
                When everything is right, press <em>Process Salary</em> to lock it.
                @if($rows->contains('outdated', true))<br><span class="warn">Attendance or salary changed after submitting for some employees (marked below) &mdash; submit again to refresh their slips.</span>@endif
            </div>
        @endif

        <form method="POST" action="{{ route('reports.salary.store') }}" id="salary-sheet">
            @csrf
            <input type="hidden" name="month" value="{{ $month }}">
            <input type="hidden" name="year" value="{{ $year }}">

            <table class="table salary-sheet">
                <colgroup>
                    <col style="width:27%"><col style="width:11%"><col style="width:10%"><col><col style="width:10%"><col style="width:10%"><col style="width:15%">
                </colgroup>
                <thead>
                <tr>
                    <th>Employee &amp; Attendance</th>
                    <th class="text-right">Salary</th>
                    <th>Deduction</th>
                    <th>Deduction Reason</th>
                    <th>Incentive</th>
                    <th class="text-right">Net Salary</th>
                    <th>Slip</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $i => $row)
                    <tr data-salary-row data-earned="{{ $row->earned_salary }}">
                        <td>
                            @unless($locked)<input type="hidden" name="rows[{{ $i }}][employee_id]" value="{{ $row->employee->id }}">@endunless
                            <div class="emp-name">{{ $row->employee->name }} <span class="emp-desig">{{ $row->employee->designation?->name }}</span></div>
                            <div class="att-chips">
                                <span class="att-chip att-present" title="Full Day">Full Day <b>{{ $row->present }}</b></span>
                                <span class="att-chip att-half_day" title="Half Day">Half Day <b>{{ $row->half_day }}</b></span>
                                <span class="att-chip att-absent" title="Absent">Absent <b>{{ $row->absent }}</b></span>
                            </div>
                            <div class="emp-meta">
                                Leave {{ $d($row->paid_leave) }} paid / {{ $d($row->unpaid_leave) }} unpaid &middot; {{ $d($row->payable_days) }} payable days
                                @if($row->not_marked) &middot; <span class="warn">{{ $row->not_marked }} not marked</span>@endif
                                @if($row->monthly_salary <= 0) &middot; <span class="warn">salary not set</span>@endif
                            </div>
                            @if($row->outdated)<div class="emp-meta"><span class="warn">&#9888; Changed since submit &mdash; submit again</span></div>@endif
                            @if($locked && ! $row->slip)<div class="emp-meta"><span class="warn">Not included in processed salary</span></div>@endif
                        </td>
                        <td class="text-right">
                            {{ number_format($row->monthly_salary, 2) }}
                            @if($row->leave_deduction > 0)<div class="leave-cut">&minus; {{ number_format($row->leave_deduction, 2) }} leave</div>@endif
                        </td>
                        @if($locked)
                            <td>{{ number_format($row->deduction, 2) }}</td>
                            <td class="wrap">{{ $row->deduction_reason ?: '-' }}</td>
                            <td>{{ number_format($row->incentive, 2) }}</td>
                        @else
                            <td><input type="number" step="0.01" min="0" class="form-control" data-field="deduction" name="rows[{{ $i }}][deduction]" value="{{ old("rows.$i.deduction", $row->deduction ?: '') }}" placeholder="0.00"></td>
                            <td><input type="text" maxlength="255" class="form-control" name="rows[{{ $i }}][deduction_reason]" value="{{ old("rows.$i.deduction_reason", $row->deduction_reason) }}" placeholder="Reason"></td>
                            <td><input type="number" step="0.01" min="0" class="form-control" data-field="incentive" name="rows[{{ $i }}][incentive]" value="{{ old("rows.$i.incentive", $row->incentive ?: '') }}" placeholder="0.00"></td>
                        @endif
                        <td class="text-right"><strong data-net>{{ number_format($row->net_salary, 2) }}</strong></td>
                        <td>
                            @if($row->slip)
                                <span class="slip-tag {{ $row->slip->isProcessed() ? 'final' : 'draft' }}">{{ $row->slip->isProcessed() ? 'Final' : 'Draft' }}</span><br>
                                <a class="btn btn-secondary btn-sm" href="{{ route('salary-slips.show', $row->slip) }}" target="_blank">View</a>
                                <a class="btn btn-primary btn-sm" href="{{ route('salary-slips.download', $row->slip) }}">PDF</a>
                                @unless($row->slip->isProcessed())
                                    <button type="submit" form="delete-slip-{{ $row->slip->id }}" class="btn btn-sm btn-row-delete" title="Delete this employee's submitted salary">Delete</button>
                                @endunless
                            @else
                                <span class="text-muted">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No employees found.</td></tr>
                @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                <tfoot>
                <tr>
                    <th>Total ({{ $rows->count() }} employees)</th>
                    <th class="text-right">{{ number_format($totals->monthly_salary, 2) }}</th>
                    <th data-total="deduction">{{ number_format($totals->deduction, 2) }}</th>
                    <th></th>
                    <th data-total="incentive">{{ number_format($totals->incentive, 2) }}</th>
                    <th class="text-right" data-total="net">{{ number_format($totals->net_salary, 2) }}</th>
                    <th></th>
                </tr>
                </tfoot>
                @endif
            </table>

            <div class="salary-actions">
                <a class="btn btn-success" href="{{ route('reports.salary.excel', ['month' => $month, 'year' => $year]) }}">Export to Excel</a>
                @if($rows->isNotEmpty() && ! $isFuture && ! $locked)
                    <button type="submit" name="action" value="submit" class="btn btn-secondary">
                        {{ $monthStatus === 'submitted' ? 'Update Submitted Salary' : 'Submit Salary' }}
                    </button>
                    <button type="submit" name="action" value="process" class="btn btn-process"
                            onclick="return confirm('Process salary for {{ $periodLabel }}?\n\nThe values on screen will be saved and LOCKED. Final salary slips will be generated and attendance for this month can no longer be changed.\n\nTo change it later you will have to delete and regenerate the whole month.')">
                        Process Salary &amp; Lock
                    </button>
                @endif
            </div>
        </form>

        {{-- One small form per submitted row (forms can't be nested inside the sheet form). --}}
        @foreach($rows as $row)
            @if($row->slip && ! $row->slip->isProcessed())
                <form id="delete-slip-{{ $row->slip->id }}" method="POST" action="{{ route('salary-slips.destroy', $row->slip) }}" style="display:none;"
                      onsubmit="return confirm('Delete submitted salary of {{ addslashes($row->employee->name) }}? Deduction, reason and incentive for this employee will be cleared.')">
                    @csrf @method('DELETE')
                </form>
            @endif
        @endforeach

        @if($monthStatus === 'submitted')
            <form method="POST" action="{{ route('reports.salary.destroy') }}" class="salary-actions" style="margin-top:8px;"
                  onsubmit="return confirm('Discard submitted salary for {{ $periodLabel }}? All entered deductions and incentives for this month will be removed.')">
                @csrf @method('DELETE')
                <input type="hidden" name="month" value="{{ $month }}"><input type="hidden" name="year" value="{{ $year }}">
                <button class="btn btn-link-danger">Discard submitted salary</button>
            </form>
        @endif

        @if($locked)
            <details class="regen-box" {{ $errors->has('confirm') ? 'open' : '' }}>
                <summary>Delete &amp; Regenerate salary for {{ $periodLabel }}</summary>
                <p class="text-muted">Use this only if a processed salary is wrong. All slips for {{ $periodLabel }} are permanently removed,
                    attendance is unlocked, and you can correct it and process the month again.</p>
                <form method="POST" action="{{ route('reports.salary.destroy') }}">
                    @csrf @method('DELETE')
                    <input type="hidden" name="month" value="{{ $month }}"><input type="hidden" name="year" value="{{ $year }}">
                    <div class="form-row">
                        <div class="form-group" style="max-width:320px;">
                            <label for="confirm">Type DELETE to confirm *</label>
                            <input type="text" id="confirm" name="confirm" class="form-control" autocomplete="off" required>
                        </div>
                    </div>
                    <button class="btn btn-danger">Delete processed salary</button>
                </form>
            </details>
        @endif

    </div>
</div>

<style>
    .salary-note { margin: 14px 0 10px; font-size: 13px; }
    table.salary-sheet th.text-right, table.salary-sheet td.text-right { text-align: right; }
    table.salary-sheet { table-layout: fixed; }
    table.salary-sheet th, table.salary-sheet td { white-space: normal; padding: 10px 8px; vertical-align: middle; }
    table.salary-sheet .form-control { width: 100%; min-width: 0; padding: 6px 8px; font-size: 13px; }
    .emp-name { font-weight: 700; }
    .emp-desig { font-weight: 400; color: #6b7280; font-size: 12px; }
    .emp-meta { font-size: 12px; color: #6b7280; margin-top: 4px; }
    .emp-meta .warn { color: #b45309; }
    .att-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 5px; }
    .att-chip { font-size: 11px; padding: 2px 8px; border-radius: 20px; border: 1px solid; white-space: nowrap; }
    .att-chip b { margin-left: 2px; }
    .att-present { background: #dcfce7; color: #166534; border-color: #86efac; }
    .att-half_day { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
    .att-absent { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
    .leave-cut { font-size: 12px; color: #b91c1c; }
    table.salary-sheet td .btn-sm { margin: 2px 0; padding: 4px 8px; }
    .salary-sheet tr.net-negative [data-net] { color: #b91c1c; }
    .salary-steps { display: flex; gap: 8px; margin: 4px 0 12px; flex-wrap: wrap; }
    .salary-steps .step { flex: 1; min-width: 200px; padding: 8px 12px; border-radius: 8px; border: 1px solid #e5e7eb; background: #fafafa; color: #6b7280; font-size: 13px; }
    .salary-steps .step b { display: inline-block; width: 20px; height: 20px; line-height: 20px; text-align: center; border-radius: 50%; background: #d1d5db; color: #fff; font-size: 11px; margin-right: 6px; }
    .salary-steps .step.done { color: #166534; border-color: #bbf7d0; background: #f0fdf4; }
    .salary-steps .step.done b { background: #16a34a; }
    .salary-steps .step.current { color: #111; border-color: #c02026; background: #fff; font-weight: 600; }
    .salary-steps .step.current b { background: #c02026; }
    .salary-banner { padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; font-size: 13px; }
    .salary-banner .warn { color: #b45309; font-weight: 600; }
    .banner-locked { background: #f0fdf4; border: 1px solid #bbf7d0; color: #14532d; }
    .banner-submitted { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; }
    .salary-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; flex-wrap: wrap; }
    .btn-process { background: #c02026; color: #fff; }
    .btn-process:hover { background: #9a1824; }
    .btn-link-danger { background: none; border: 0; color: #b91c1c; text-decoration: underline; padding: 4px 0; font-size: 13px; cursor: pointer; }
    .slip-tag { display: inline-block; font-size: 10px; font-weight: 700; text-transform: uppercase; padding: 1px 6px; border-radius: 4px; margin-bottom: 3px; }
    .slip-tag.draft { background: #fef3c7; color: #92400e; }
    .slip-tag.final { background: #dcfce7; color: #166534; }
    .regen-box { margin-top: 24px; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 16px; background: #fff7f7; }
    .regen-box summary { cursor: pointer; font-weight: 700; color: #b91c1c; }
    .regen-box p { margin: 10px 0; font-size: 13px; }
    .btn-row-delete { background: #fff; color: #b91c1c; border: 1px solid #fca5a5; }
    .btn-row-delete:hover { background: #fee2e2; }
</style>

@unless($locked)
<script>
(function () {
    const sheet = document.getElementById('salary-sheet');
    if (!sheet) return;
    const money = v => v.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = el => { const v = parseFloat(el && el.value); return isNaN(v) ? 0 : v; };

    function recalc() {
        let ded = 0, inc = 0, net = 0;
        sheet.querySelectorAll('[data-salary-row]').forEach(row => {
            const d = num(row.querySelector('[data-field="deduction"]'));
            const i = num(row.querySelector('[data-field="incentive"]'));
            const n = Math.round((parseFloat(row.dataset.earned) - d + i) * 100) / 100;
            row.querySelector('[data-net]').textContent = money(n);
            row.classList.toggle('net-negative', n < 0);
            ded += d; inc += i; net += n;
        });
        const set = (key, val, rupee) => document.querySelectorAll('[data-total="' + key + '"]').forEach(el => el.textContent = (rupee ? '₹' : '') + money(val));
        set('deduction', ded); set('incentive', inc); set('net', net);
    }

    sheet.addEventListener('input', e => { if (e.target.dataset.field) recalc(); });
    recalc();
})();
</script>
@endunless
@endsection
