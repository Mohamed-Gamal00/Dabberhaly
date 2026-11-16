<div class="dropdown d-inline-block">
    <button type="button" class="btn header-item noti-icon waves-effect" id="page-header-notifications-dropdown"
        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="mdi mdi-bell-outline"></i>
        @if ($newCount)
            <span class="badge bg-danger rounded-pill">{{ $newCount }}</span>
        @endif
    </button>
    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
        aria-labelledby="page-header-notifications-dropdown">
        <div class="p-3">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="m-0 font-size-16"> اشعارات ({{ $newCount }}) </h5>
                </div>
            </div>
        </div>

        <div data-simplebar style="max-height: 250px;">
            @forelse($notifications as $notification)
                <a href="{{ route('notifications.read', ['notification_id' => $notification->id, 'url' => $notification->data['url'] ?? '#']) }}"
                    class="text-reset notification-item {{ $notification->read_at ? 'opacity-50' : '' }}">
                    <div class="d-flex align-items-start px-3 py-2">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar-xs">
                                <span class="avatar-title {{ $notification->read_at ? 'bg-secondary' : 'bg-success' }} rounded-circle font-size-16">
                                    <i class="mdi mdi-cart-outline"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 {{ $notification->read_at ? 'text-muted fw-normal' : 'fw-bold' }}">
                                {{ $notification->data['title'] ?? '' }}
                            </h6>
                            <p class="mb-0 font-size-12 {{ $notification->read_at ? 'text-muted' : '' }}">
                                {{ $notification->data['body'] ?? '' }}
                            </p>
                            <p class="mb-0 font-size-11 text-muted">
                                {{ $notification->created_at->diffForHumans() }}
                                @if ($notification->read_at)
                                    &nbsp;·&nbsp;<span class="text-success">تمت القراءة</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </a>
            @empty
                <div class="text-center p-3 text-muted">لا يوجد اشعارات</div>
            @endforelse
        </div>

        <div class="p-2 border-top">
            <div class="d-grid">
                <a class="btn btn-sm btn-link font-size-14 text-center" href="{{ route('notifications.index') }}">
                    مشاهدة الكل
                </a>
            </div>
        </div>
    </div>
</div>
