@extends('layouts.app')

@section('title', 'Staff Management - School Examination & Report Card Management System')
@section('page_title', 'Staff Management')

@section('content')
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0;">Staff Account Management</h3>
        @can('create', App\Models\User::class)
            <button type="button" class="btn btn-primary" id="btn-create-user" onclick="openCreateUserModal()">
                + Create Staff
            </button>
        @endcan
    </div>

    <!-- Filters and Search Card -->
    <div style="padding: 1.25rem; background: var(--color-bg-subtle, #f9fafb); border-bottom: 1px solid var(--color-border, #e5e7eb);">
        <form method="GET" action="{{ route('users.index') }}">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="search" class="form-label" style="font-weight: 600;">Search Staff</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Search by username or display name..." value="{{ request('search') }}">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="role_id" class="form-label">Role</label>
                    <select id="role_id" name="role_id" class="form-control">
                        <option value="">All Roles</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" {{ (string) request('role_id') === (string) $role->id ? 'selected' : '' }}>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter_subject_id" class="form-label">Subject Eligibility</label>
                    <select id="filter_subject_id" name="subject_id" class="form-control">
                        <option value="">All Eligible Subjects</option>
                        @foreach($subjects as $subj)
                            <option value="{{ $subj->id }}" {{ (string) request('subject_id') === (string) $subj->id ? 'selected' : '' }}>
                                {{ $subj->name }} ({{ $subj->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary" id="btn-filter-users">Filter</button>
                <a href="{{ route('users.index') }}" class="btn btn-secondary" id="btn-reset-users">Reset</a>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Display Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Subject Eligibility</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td><strong>{{ $user->username }}</strong></td>
                        <td>{{ $user->display_name }}</td>
                        <td>{{ $user->email ?? '—' }}</td>
                        <td>
                            @php
                                $badgeClass = match($user->role?->name) {
                                    'Administrator' => 'badge-primary',
                                    'Office Staff' => 'badge-secondary',
                                    'Class Teacher' => 'badge-info',
                                    'Subject Teacher' => 'badge-secondary',
                                    default => 'badge-secondary'
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}">
                                {{ $user->role?->name ?? 'None' }}
                            </span>
                        </td>
                        <td>
                            @if(in_array($user->role?->name, ['Class Teacher', 'Subject Teacher']) && !empty($user->eligible_subject_ids))
                                @php
                                    $eligibleNames = $subjects->whereIn('id', $user->eligible_subject_ids)->pluck('name')->implode(', ');
                                @endphp
                                <span style="font-size: var(--font-size-sm); color: var(--color-text-main);">
                                    {{ $eligibleNames ?: '—' }}
                                </span>
                            @else
                                <span style="color: var(--color-text-muted);">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $user->is_active ? 'badge-success' : 'badge-danger' }}">
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td style="font-size: var(--font-size-xs); color: var(--color-text-muted);">
                            {{ $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i') : 'Never' }}
                        </td>
                        <td>
                            <div class="row-actions">
                                @can('update', $user)
                                    <button type="button" class="btn btn-secondary btn-sm"
                                        onclick="openEditUserModal({{ json_encode([
                                            'id' => $user->id,
                                            'username' => $user->username,
                                            'display_name' => $user->display_name,
                                            'email' => $user->email,
                                            'role_id' => $user->role_id,
                                            'role_name' => $user->role?->name,
                                            'is_admin' => $user->isAdmin(),
                                            'eligible_subject_ids' => $user->eligible_subject_ids ?? [],
                                        ]) }})">
                                        Edit
                                    </button>
                                @endcan

                                @can('changePassword', $user)
                                    <button type="button" class="btn btn-secondary btn-sm"
                                        onclick="openChangePasswordModal({{ $user->id }}, '{{ addslashes($user->username) }}')">
                                        Password
                                    </button>
                                @endcan

                                @if($user->is_active)
                                    @can('deactivate', $user)
                                        <form method="POST" action="{{ route('users.deactivate', $user) }}" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary btn-sm"
                                                onclick="return confirm('Are you sure you want to deactivate user \'{{ addslashes($user->username) }}\'?');">
                                                Deactivate
                                            </button>
                                        </form>
                                    @endcan
                                @else
                                    @can('activate', $user)
                                        <form method="POST" action="{{ route('users.activate', $user) }}" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary btn-sm"
                                                onclick="return confirm('Are you sure you want to activate user \'{{ addslashes($user->username) }}\'?');">
                                                Activate
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                            No user accounts found matching criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
        <div style="padding: 1rem; border-top: 1px solid var(--color-border, #e5e7eb);">
            {{ $users->links() }}
        </div>
    @endif
</div>

<!-- Modal: Create User -->
<div id="create-user-modal" class="modal-backdrop" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Create New Staff Account</h3>
            <button type="button" class="modal-close" onclick="closeModal('create-user-modal')">&times;</button>
        </div>
        <form id="create-user-form" method="POST" action="{{ route('users.store') }}" autocomplete="off">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Username <span style="color: var(--color-danger);">*</span></label>
                    <input type="text" id="create_username" name="username" class="form-control" required maxlength="100" placeholder="e.g. kumar" value="{{ old('username') }}" autocomplete="off">
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Lowercase letters, numbers, dots, dashes, and underscores only.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Display Name <span style="color: var(--color-danger);">*</span></label>
                    <input type="text" id="create_display_name" name="display_name" class="form-control" required maxlength="150" placeholder="e.g. Kumar" value="{{ old('display_name') }}" autocomplete="off">
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address <span style="color: var(--color-danger);">*</span></label>
                    <input type="email" id="create_email" name="email" class="form-control" required maxlength="255" placeholder="e.g. Kumar@school.test" value="{{ old('email') }}" autocomplete="off">
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Must be unique and lowercase. Used for two-step verification.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Role <span style="color: var(--color-danger);">*</span></label>
                    <select id="create_role_id" name="role_id" class="form-control" required onchange="updateCreateEligibility()">
                        <option value="">Select Role</option>
                        @foreach($roles as $role)
                            @if($role->name !== 'Administrator')
                                <option value="{{ $role->id }}" data-role-name="{{ $role->name }}" {{ (string) old('role_id') === (string) $role->id ? 'selected' : '' }}>
                                    {{ $role->name }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <div class="form-group" id="create_subject_eligibility_group" style="display: none;">
                    <label class="form-label">Subject Eligibility (Informational)</label>
                    <div style="max-height: 160px; overflow-y: auto; border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.5rem; background: var(--color-bg-surface);">
                        @foreach($subjects as $subj)
                            <label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem; font-size: var(--font-size-sm); cursor: pointer;">
                                <input type="checkbox" name="eligible_subject_ids[]" value="{{ $subj->id }}" class="create_eligible_subject_cb">
                                <span>{{ $subj->name }} <span style="color: var(--color-text-muted); font-size: var(--font-size-xs);">({{ $subj->code }})</span></span>
                            </label>
                        @endforeach
                    </div>
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Select all subjects this teacher is qualified to teach. One teacher can have multiple eligible subjects.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Password <span style="color: var(--color-danger);">*</span></label>
                    <input type="password" id="create_password" name="password" class="form-control" required minlength="8" placeholder="Minimum 8 characters" autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm Password <span style="color: var(--color-danger);">*</span></label>
                    <input type="password" id="create_password_confirmation" name="password_confirmation" class="form-control" required minlength="8" placeholder="Repeat password" autocomplete="new-password">
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="is_active" id="create_is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                    <label for="create_is_active" style="font-size: var(--font-size-sm); cursor: pointer; margin-bottom: 0;">Active Account</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('create-user-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit User -->
<div id="edit-user-modal" class="modal-backdrop" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Edit User Account</h3>
            <button type="button" class="modal-close" onclick="closeModal('edit-user-modal')">&times;</button>
        </div>
        <form id="edit-user-form" method="POST" action="">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Username <span style="color: var(--color-danger);">*</span></label>
                    <input type="text" id="edit_username" name="username" class="form-control" required maxlength="100">
                </div>

                <div class="form-group">
                    <label class="form-label">Display Name <span style="color: var(--color-danger);">*</span></label>
                    <input type="text" id="edit_display_name" name="display_name" class="form-control" required maxlength="150">
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" id="edit_email" name="email" class="form-control" maxlength="255">
                </div>

                <div class="form-group">
                    <label class="form-label">Role <span style="color: var(--color-danger);">*</span></label>
                    <select id="edit_role_id" name="role_id" class="form-control" required onchange="updateEditEligibility()">
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" data-role-name="{{ $role->name }}">
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                    <small id="edit_admin_role_note" style="display: none; color: var(--color-text-muted); font-size: var(--font-size-xs);">
                        Administrator role is preserved and cannot be changed via standard management.
                    </small>
                </div>

                <div class="form-group" id="edit_subject_eligibility_group" style="display: none;">
                    <label class="form-label">Subject Eligibility (Informational)</label>
                    <div style="max-height: 160px; overflow-y: auto; border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.5rem; background: var(--color-bg-surface);">
                        @foreach($subjects as $subj)
                            <label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem; font-size: var(--font-size-sm); cursor: pointer;">
                                <input type="checkbox" name="eligible_subject_ids[]" value="{{ $subj->id }}" class="edit_eligible_subject_cb" id="edit_subj_{{ $subj->id }}">
                                <span>{{ $subj->name }} <span style="color: var(--color-text-muted); font-size: var(--font-size-xs);">({{ $subj->code }})</span></span>
                            </label>
                        @endforeach
                    </div>
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Select all subjects this teacher is qualified to teach.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-user-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Change Password -->
<div id="change-password-modal" class="modal-backdrop" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Change Password</h3>
            <button type="button" class="modal-close" onclick="closeModal('change-password-modal')">&times;</button>
        </div>
        <form id="change-password-form" method="POST" action="">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Target User</label>
                    <input type="text" id="change_password_username" class="form-control" readonly disabled>
                </div>

                <div class="form-group">
                    <label class="form-label">New Password <span style="color: var(--color-danger);">*</span></label>
                    <input type="password" name="password" class="form-control" required minlength="8" placeholder="Minimum 8 characters">
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm New Password <span style="color: var(--color-danger);">*</span></label>
                    <input type="password" name="password_confirmation" class="form-control" required minlength="8" placeholder="Repeat new password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('change-password-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateCreateEligibility() {
    const roleSelect = document.getElementById('create_role_id');
    const selectedOpt = roleSelect.options[roleSelect.selectedIndex];
    const roleName = selectedOpt ? selectedOpt.getAttribute('data-role-name') : '';
    const group = document.getElementById('create_subject_eligibility_group');
    if (roleName === 'Class Teacher' || roleName === 'Subject Teacher') {
        group.style.display = 'block';
    } else {
        group.style.display = 'none';
        document.querySelectorAll('.create_eligible_subject_cb').forEach(cb => cb.checked = false);
    }
}

function updateEditEligibility() {
    const roleSelect = document.getElementById('edit_role_id');
    const selectedOpt = roleSelect.options[roleSelect.selectedIndex];
    const roleName = selectedOpt ? selectedOpt.getAttribute('data-role-name') : '';
    const group = document.getElementById('edit_subject_eligibility_group');
    if (roleName === 'Class Teacher' || roleName === 'Subject Teacher') {
        group.style.display = 'block';
    } else {
        group.style.display = 'none';
        document.querySelectorAll('.edit_eligible_subject_cb').forEach(cb => cb.checked = false);
    }
}

function openCreateUserModal() {
    const form = document.getElementById('create-user-form');
    if (form) {
        @if(!$errors->any())
            form.reset();
            const u = document.getElementById('create_username');
            const d = document.getElementById('create_display_name');
            const e = document.getElementById('create_email');
            const p = document.getElementById('create_password');
            const pc = document.getElementById('create_password_confirmation');
            if (u) u.value = '';
            if (d) d.value = '';
            if (e) e.value = '';
            if (p) p.value = '';
            if (pc) pc.value = '';
            document.querySelectorAll('.create_eligible_subject_cb').forEach(cb => cb.checked = false);
        @endif
    }
    updateCreateEligibility();
    openModal('create-user-modal');
}

function openEditUserModal(user) {
    const form = document.getElementById('edit-user-form');
    form.action = '/users/' + user.id;

    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_display_name').value = user.display_name;
    document.getElementById('edit_email').value = user.email || '';

    const roleSelect = document.getElementById('edit_role_id');
    roleSelect.value = user.role_id;

    const adminNote = document.getElementById('edit_admin_role_note');
    if (user.is_admin) {
        // Disable other options for Administrator
        for (let i = 0; i < roleSelect.options.length; i++) {
            if (roleSelect.options[i].getAttribute('data-role-name') !== 'Administrator') {
                roleSelect.options[i].disabled = true;
            } else {
                roleSelect.options[i].disabled = false;
            }
        }
        adminNote.style.display = 'block';
    } else {
        // Hide Administrator option for non-admins
        for (let i = 0; i < roleSelect.options.length; i++) {
            if (roleSelect.options[i].getAttribute('data-role-name') === 'Administrator') {
                roleSelect.options[i].disabled = true;
            } else {
                roleSelect.options[i].disabled = false;
            }
        }
        adminNote.style.display = 'none';
    }

    const eligibleIds = (user.eligible_subject_ids || []).map(Number);
    document.querySelectorAll('.edit_eligible_subject_cb').forEach(cb => {
        cb.checked = eligibleIds.includes(Number(cb.value));
    });
    updateEditEligibility();

    openModal('edit-user-modal');
}

function openChangePasswordModal(userId, username) {
    const form = document.getElementById('change-password-form');
    form.action = '/users/' + userId + '/password';
    document.getElementById('change_password_username').value = username;
    openModal('change-password-modal');
}
</script>
@endsection
