<?php

namespace App\Http\Controllers;

use App\Models\NumberSetting;
use Illuminate\Http\Request;

class NumberSettingController extends Controller
{
    public function index()
    {
       /* foreach (['quotation', 'invoice'] as $type) {
            NumberSetting::firstOrCreate(
                ['document_type' => $type],
                [
                    'prefix' => strtoupper(substr($type, 0, 3)) . '-',
                    'postfix' => '',
                    'next_number' => 1,
                    'number_padding' => 4,
                ]
            );
        }

        $settings = NumberSetting::orderBy('document_type')->get();*/
         NumberSetting::firstOrCreate(
            ['document_type' => 'quotation'],
            [
                'prefix' => 'QUO-',
                'postfix' => '',
                'next_number' => 1,
                'number_padding' => 4,
            ]
        );

        $settings = NumberSetting::where('document_type', 'quotation')->get();

        return view('number-settings.index', compact('settings'));
    }

    public function update(Request $request, NumberSetting $numberSetting)
    {
        $data = $request->validate([
            'prefix' => ['nullable', 'string', 'max:50'],
            'postfix' => ['nullable', 'string', 'max:50'],
            'next_number' => ['required', 'integer', 'min:1'],
            'number_padding' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $numberSetting->update($data);

        return back()->with('success', 'Number setting updated successfully.');
    }
}
