@extends('layouts.app')

@section('page_title', 'Canned replies')
@section('page_subtitle', 'Reusable responses. Placeholders: {requester}, {agent}, {reference}')

@section('page_actions')
    <a href="{{ route('canned-responses.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> New canned reply</a>
@stop

@section('body_content')
    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>Title</th><th>Preview</th><th>Scope</th><th>Department</th><th></th></tr></thead>
                <tbody>
                @forelse ($responses as $r)
                    <tr>
                        <td class="fw-semibold">{{ $r->title }}</td>
                        <td class="small text-body-secondary">{{ \Illuminate\Support\Str::limit($r->body, 90) }}</td>
                        <td>{!! $r->isShared() ? '<span class="badge text-bg-info">Shared</span>' : '<span class="badge text-bg-secondary">Personal</span>' !!}</td>
                        <td>{{ $r->department?->name ?? '—' }}</td>
                        <td class="text-end text-nowrap">
                            @can('update', $r)
                                <a href="{{ route('canned-responses.edit', $r) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('canned-responses.destroy', $r) }}" class="d-inline" onsubmit="return confirm('Delete this canned reply?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-body-secondary py-4">No canned replies yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop
