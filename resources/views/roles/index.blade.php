@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('content')
    <p class="text-muted">Tick what each role may do. Changes apply from the next page a person opens. The Administrator role always keeps every permission, every role keeps access to its own pages, and the user, settings and backup permissions can only belong to the Administrator.</p>

    <form method="POST" action="{{ route('roles.update') }}" data-confirm="Save these permission changes? They affect every user with those roles.">
        @csrf

        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Permission</th>
                            @foreach ($roles as $role)
                                <th class="text-center">{{ $role->label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($permissions as $module => $items)
                            <tr class="table-light"><td colspan="{{ $roles->count() + 1 }}" class="fw-semibold text-uppercase small">{{ str_replace('_', ' ', $module) }}</td></tr>
                            @foreach ($items as $permission)
                                <tr>
                                    <td>{{ $permission->label }}<div class="small text-muted"><code>{{ $permission->key }}</code></div></td>
                                    @foreach ($roles as $role)
                                        @php
                                            $adminOnly = $role->name !== 'admin' && in_array($permission->key, $reserved, true);
                                            $locked = $role->name === 'admin' || $permission->key === 'self.access' || $adminOnly;
                                            $checked = $adminOnly ? false : ($locked || $role->permissions->contains('id', $permission->id));
                                        @endphp
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input" @checked($checked) @disabled($locked)
                                                   @unless ($locked) name="permissions[{{ $role->id }}][]" value="{{ $permission->id }}" @endunless
                                                   aria-label="{{ $role->label }}: {{ $permission->label }}">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="small text-muted">Every change is written to the audit log.</span>
                <button type="submit" class="btn btn-primary">Save permissions</button>
            </div>
        </div>
    </form>
@endsection
