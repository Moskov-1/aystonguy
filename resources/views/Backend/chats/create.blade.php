@extends('Backend.Layouts.Dashboard.master')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Create Chat</h5>
            </div>
            <div class="card-body">
                <form id="chat-form" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">User</label>
                        <select name="user_id" class="form-select">
                            <option value="">System (No User)</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" id="chat-type" class="form-select">
                            <option value="text">Text</option>
                            <option value="image">Image</option>
                            <option value="voice">Voice</option>
                        </select>
                    </div>

                    <div class="mb-3" id="message-group">
                        <label class="form-label">Message</label>
                        <textarea name="message" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="mb-3 d-none" id="file-group">
                        <label class="form-label">File (Image/Voice)</label>
                        <input type="file" name="file" class="form-control">
                    </div>

                    <button type="submit" class="btn btn-primary" id="save-btn">Save</button>
                    <a href="{{ route('admin.chats.index') }}" class="btn btn-secondary">Back</a>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(function() {
            $('#chat-type').on('change', function() {
                var type = $(this).val();
                if (type === 'text') {
                    $('#message-group').removeClass('d-none');
                    $('#file-group').addClass('d-none');
                } else {
                    $('#message-group').addClass('d-none');
                    $('#file-group').removeClass('d-none');
                }
            });

            $('#chat-form').on('submit', function(e) {
                e.preventDefault();
                var formData = new FormData(this);
                $('#save-btn').prop('disabled', true);

                $.ajax({
                    url: "{{ route('admin.chats.store') }}",
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        Swal.fire('Success', response.success, 'success').then(() => {
                            window.location.href = "{{ route('admin.chats.index') }}";
                        });
                    },
                    error: function(xhr) {
                        $('#save-btn').prop('disabled', false);
                        var errors = xhr.responseJSON.errors;
                        var errorMsg = '';
                        $.each(errors, function(key, value) {
                            errorMsg += value[0] + '<br>';
                        });
                        Swal.fire('Error', errorMsg, 'error');
                    }
                });
            });
        });
    </script>
@endpush
