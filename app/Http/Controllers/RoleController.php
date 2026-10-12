<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Which role may do what. The Admin role always has everything, and everyone keeps access to their own pages. */
class RoleController extends Controller
{
    /** Only the Administrator role may hold these, so nobody can raise their own access through this page. */
    public const RESERVED = ['users.manage', 'settings.manage', 'backup.manage'];

    public function index()
    {
        return view('roles.index', [
            'roles' => Role::with('permissions')->orderBy('id')->get(),
            'permissions' => Permission::orderBy('module')->orderBy('id')->get()->groupBy('module'),
            'reserved' => self::RESERVED,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['integer', 'exists:permissions,id'],
        ]);

        $selfAccess = Permission::where('key', 'self.access')->value('id');
        $reservedIds = Permission::whereIn('key', self::RESERVED)->pluck('id');
        $changes = [];

        DB::transaction(function () use ($data, $selfAccess, $reservedIds, &$changes) {
            foreach (Role::with('permissions')->where('name', '!=', 'admin')->get() as $role) {
                $wanted = collect($data['permissions'][$role->id] ?? [])->map(fn ($id) => (int) $id)->reject(fn ($id) => $reservedIds->contains($id))->push($selfAccess)->unique()->values();
                $current = $role->permissions->pluck('id');

                $added = $wanted->diff($current);
                $removed = $current->diff($wanted);

                if ($added->isEmpty() && $removed->isEmpty()) {
                    continue;
                }

                $role->permissions()->sync($wanted->all());

                $keys = Permission::whereIn('id', $added->merge($removed))->pluck('key', 'id');
                $changes[] = $role->label . ': ' . $added->map(fn ($id) => '+' . $keys[$id])->merge($removed->map(fn ($id) => '-' . $keys[$id]))->implode(', ');
            }
        });

        if ($changes) {
            AuditService::log('permissions_changed', 'roles', 'Permissions changed. ' . implode(' | ', $changes));
        }

        return back()->with('success', $changes ? 'The permissions have been saved. They apply from the next page each person opens.' : 'Nothing was changed.');
    }
}
