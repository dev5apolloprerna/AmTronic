@extends('layouts.app')

@section('title', 'Number Settings')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Document Number Settings</h3>
        </div>
        <div class="card-body">
            <p class="text-muted">Set the prefix, postfix and next running number used to auto-generate Quotation and Invoice numbers. Example: prefix <code>QUO-2026-</code> + number <code>1</code> padded to 4 digits = <code>QUO-2026-0001</code>.</p>

            @foreach($settings as $setting)
                <form method="POST" action="{{ route('number-settings.update', $setting) }}" style="margin-bottom:24px; padding-bottom:20px; border-bottom:1px solid var(--color-border);">
                    @csrf
                    @method('PUT')
                    <h4 style="text-transform:capitalize; margin-top:0;">{{ $setting->document_type }} Numbering</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Prefix</label>
                            <input type="text" class="form-control" name="prefix" value="{{ $setting->prefix }}">
                        </div>
                        <div class="form-group">
                            <label>Next Number</label>
                            <input type="number" min="1" class="form-control" name="next_number" value="{{ $setting->next_number }}">
                        </div>
                        <div class="form-group">
                            <label>Number Padding (digits)</label>
                            <input type="number" min="1" max="10" class="form-control" name="number_padding" value="{{ $setting->number_padding }}">
                        </div>
                        <div class="form-group">
                            <label>Postfix</label>
                            <input type="text" class="form-control" name="postfix" value="{{ $setting->postfix }}">
                        </div>
                    </div>
                    <div class="form-hint" style="margin-bottom:12px;">
                        Preview next number: <strong>{{ $setting->prefix }}{{ str_pad($setting->next_number, $setting->number_padding, '0', STR_PAD_LEFT) }}{{ $setting->postfix }}</strong>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Save {{ ucfirst($setting->document_type) }} Setting</button>
                </form>
            @endforeach
        </div>
    </div>
@endsection
