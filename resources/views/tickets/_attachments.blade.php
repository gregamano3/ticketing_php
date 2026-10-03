@if ($attachments->isNotEmpty())
    <div class="d-flex flex-wrap gap-2 mt-2">
        @foreach ($attachments as $file)
            <div class="border rounded px-2 py-1 small d-flex align-items-center gap-2">
                <i class="{{ $file->icon }} fs-5"></i>
                <a href="{{ route('attachments.show', $file) }}" target="_blank" rel="noopener">{{ $file->original_name }}</a>
                <span class="text-body-secondary">{{ $file->human_size }}</span>
                @can('delete', $file)
                    <form method="POST" action="{{ route('attachments.destroy', $file) }}" class="d-inline" onsubmit="return confirm('Remove this attachment?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-link btn-sm p-0 text-danger" title="Remove"><i class="bi bi-x-lg"></i></button>
                    </form>
                @endcan
            </div>
        @endforeach
    </div>
@endif
