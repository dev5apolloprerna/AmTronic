<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $users = User::with('designation')
            ->withSum('advances as advance_given', 'adv_amount')
            ->withSum('advances as advance_returned', 'return_amount')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('designation', fn ($d) => $d->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', compact('users', 'search'));
    }

    public function create()
    {
        return view('users.create', [
            'designations' => Designation::selectable(),
        ]);
    }

    public function store(Request $request)
    {
        $loginRequired = $this->grantsLogin($request);

        $data = $request->validate($this->rules(null, $loginRequired));

        // Employees who cannot log in get no password at all (the key is left
        // out, so the column stays NULL).
        if ($loginRequired) {
            $data['password'] = Hash::make($data['password']);
        }

        User::create($data);

        return redirect()->route('users.index')->with('success', 'Employee created successfully.');
    }

    public function edit(User $user)
    {
        return view('users.edit', [
            'user' => $user,
            'designations' => Designation::selectable($user->designation_id),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $loginRequired = $this->grantsLogin($request);

        // Don't let an admin edit their own account into one that can no longer log in.
        if ($user->id === $request->user()->id && ! $loginRequired) {
            return back()->withInput()->with('error', 'You cannot change your own account so that it can no longer log in.');
        }

        $data = $request->validate($this->rules($user, $loginRequired));

        // A blank password keeps the current one. For employees who cannot log
        // in, any stored password is left untouched and none can be set.
        if ($loginRequired && ! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'Employee updated successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->quotations()->exists()) {
            return back()->with('error', 'Cannot delete an employee who has created quotations. Set them inactive instead.');
        }

        if ($user->advances()->exists()) {
            return back()->with('error', 'Cannot delete an employee who has advance records. Set them inactive instead.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Employee deleted successfully.');
    }

    /**
     * Whether the account being saved will be able to log in: a Super Admin, or
     * an employee whose designation is flagged can_login (e.g. "Sales"). Those
     * accounts need an email and a password; everyone else is a record only.
     */
    private function grantsLogin(Request $request): bool
    {
        if ($request->input('role') === 'super_admin') {
            return true;
        }

        $designationId = $request->input('designation_id');

        return is_numeric($designationId)
            && Designation::whereKey((int) $designationId)->where('can_login', true)->exists();
    }

    private function rules(?User $user, bool $loginRequired): array
    {
        $email = ['email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)];

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [$loginRequired ? 'required' : 'nullable', ...$email],
            'role' => ['required', 'in:super_admin,user'],
            'designation_id' => ['nullable', $this->designationRule($user?->designation_id)],
            'status' => ['required', 'in:active,inactive'],
        ];

        if ($loginRequired) {
            // A new login needs a password; an existing one is kept unless a new one is typed.
            $rules['password'] = [filled($user?->password) ? 'nullable' : 'required', 'string', 'min:6', 'confirmed'];
        }

        return $rules;
    }

    /**
     * A designation must exist and be active. The employee's own current
     * designation is also accepted, so editing someone whose designation was
     * later made inactive doesn't force a change.
     */
    private function designationRule(?int $currentId = null)
    {
        return Rule::exists('designations', 'id')->where(function ($q) use ($currentId) {
            $q->where(function ($qq) use ($currentId) {
                $qq->where('status', 'active');

                if ($currentId) {
                    $qq->orWhere('id', $currentId);
                }
            });
        });
    }
}
