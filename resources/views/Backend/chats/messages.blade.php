@foreach($chats as $chat)
    <div class="chat-message mb-4">
        <div class="d-flex {{ ($chat->sender_type == 'admin' || $chat->is_ai) ? 'flex-row-reverse' : '' }}">
            <div class="user-avatar flex-shrink-0 {{ ($chat->sender_type == 'admin' || $chat->is_ai) ? 'ms-3' : 'me-3' }}">
                <div class="avatar">
                    @if($chat->is_ai)
                        <div class="rounded-circle bg-dark d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px;">
                            <i class="bi bi-robot text-white fs-4"></i>
                        </div>
                    @elseif($chat->sender_type == 'user')
                        <img src="{{ auth()->user()->profile_image ? asset(auth()->user()->profile_image) : asset('assets/img/avatars/1.png') }}" alt="Avatar" class="rounded-circle shadow-sm" style="width: 40px; height: 40px; object-fit: cover;">
                    @else
                        <img src="{{ asset('assets/img/avatars/1.png') }}" alt="Avatar" class="rounded-circle shadow-sm" style="width: 40px; height: 40px; object-fit: cover;">
                    @endif
                </div>
            </div>
            <div class="chat-message-wrapper flex-grow-1" style="max-width: 80%;">
                <div class="chat-message-text p-3 shadow-sm {{ ($chat->sender_type == 'admin' || $chat->is_ai) ? 'bg-primary text-white' : 'bg-white border' }}" style="border-radius: 12px; {{ ($chat->sender_type == 'admin' || $chat->is_ai) ? 'border-bottom-right-radius: 2px;' : 'border-bottom-left-radius: 2px;' }}">
                    @if($chat->type == 'image')
                        <div class="text-center mb-2">
                            <a href="{{ asset($chat->file_path) }}" target="_blank" class="d-block">
                                <img src="{{ asset($chat->file_path) }}" class="img-fluid rounded" style="max-height: 300px; width: auto; border: 4px solid rgba(255,255,255,0.2);">
                            </a>
                        </div>
                    @elseif($chat->type == 'voice')
                        <div class="mb-2" style="min-width: 250px;">
                            <audio controls class="w-100" style="height: 35px;">
                                <source src="{{ asset($chat->file_path) }}" type="audio/webm">
                                <source src="{{ asset($chat->file_path) }}" type="audio/mpeg">
                            </audio>
                        </div>
                    @endif
                    
                    @if($chat->message)
                        <div class="message-body {{ $chat->type != 'text' ? 'mt-2 border-top pt-2' : '' }}" style="{{ $chat->type != 'text' && $chat->sender_type == 'admin' ? 'border-color: rgba(255,255,255,0.2) !important;' : '' }}">
                            <p class="mb-0" style="word-wrap: break-word; line-height: 1.5; font-size: 14px;">{{ $chat->message }}</p>
                        </div>
                    @endif
                </div>
                <div class="mt-1 d-flex align-items-center {{ $chat->sender_type == 'admin' ? 'justify-content-end' : '' }}" style="font-size: 0.65rem; color: #a1acb8;">
                    <span class="fw-medium">{{ $chat->created_at->format('h:i A') }}</span>
                    @if($chat->sender_type == 'admin')
                        <i class="bi bi-check2-all ms-1 {{ $chat->is_read ? 'text-info' : '' }}" style="font-size: 0.85rem;"></i>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endforeach
