<x-app-layout>
    <div style="margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: flex-end">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem">User Approvals</h1>
            <p style="color: var(--text-muted)">Manage access requests for the IELTS platform.</p>
        </div>
        <div class="glass" style="display: flex; padding: 4px; border-radius: 10px">
            <a href="?status=pending" class="btn {{ $status === 'pending' ? 'btn-primary' : 'btn-outline' }}" style="padding: 0.5rem 1rem; font-size: 0.875rem">Pending</a>
            <a href="?status=active" class="btn {{ $status === 'active' ? 'btn-primary' : 'btn-outline' }}" style="padding: 0.5rem 1rem; font-size: 0.875rem">Active</a>
        </div>
    </div>

    @if(session('success'))
        <div class="glass" style="padding: 1rem; margin-bottom: 1.5rem; border-color: var(--accent); color: var(--accent)">
            {{ session('success') }}
        </div>
    @endif

    <div class="glass" style="overflow: hidden">
        <table style="width: 100%; border-collapse: collapse; text-align: left">
            <thead>
                <tr style="border-bottom: 1px solid var(--glass-border); color: var(--text-muted); font-size: 0.875rem">
                    <th style="padding: 1.25rem">Name</th>
                    <th style="padding: 1.25rem">Email</th>
                    <th style="padding: 1.25rem">Target Band</th>
                    <th style="padding: 1.25rem">Status</th>
                    <th style="padding: 1.25rem; text-align: right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr style="border-bottom: 1px solid var(--glass-border)">
                        <td style="padding: 1.25rem; font-weight: 500">{{ $user->name }}</td>
                        <td style="padding: 1.25rem; color: var(--text-muted)">{{ $user->email }}</td>
                        <td style="padding: 1.25rem">
                            <span class="badge" style="background: rgba(99, 102, 241, 0.1); color: var(--primary)">
                                Band {{ $user->target_band ?? 'N/A' }}
                            </span>
                        </td>
                        <td style="padding: 1.25rem">
                            <span class="badge {{ $user->status === 'active' ? 'badge-active' : 'badge-pending' }}">
                                {{ ucfirst($user->status) }}
                            </span>
                        </td>
                        <td style="padding: 1.25rem; text-align: right">
                            @if($user->status === 'pending')
                                <form method="POST" action="{{ route('admin.users.approve', $user->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary" style="padding: 0.4rem 1rem; font-size: 0.75rem">
                                        Approve Access
                                    </button>
                                </form>
                            @else
                                <span style="color: var(--text-muted); font-size: 0.875rem">Approved ✅</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="padding: 3rem; text-align: center; color: var(--text-muted)">
                            No users found with status: <strong>{{ $status }}</strong>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
