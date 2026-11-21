@extends('dashboard.index')

@section('title', ' اضافة طلب')

@section('breadcrumb')
    @parent
    <li class="breadcrumb-item"><a href="{{ route('bulk_orders.index') }}">طلبات الشراء بالجملة</a></li>
    <li class="breadcrumb-item">انشاء طلب</li>
@endsection

@section('section')

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    {{-- Form Start --}}
                    <div class="container">
                        <form action="{{ route('bulk_orders.store') }}" method="POST">
                            @csrf
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <div class="form-group">
                                <label for="name">الاسم</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                                @error('name')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="form-group my-3">
                                <label for="phone">الهاتف</label>
                                <input type="tel" inputmode="tel" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" required>
                                @error('phone')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="form-group my-3">
                                <label for="company_name">اسم الشركة او الجهة</label>
                                <input type="text" class="form-control @error('company_name') is-invalid @enderror" id="company_name" name="company_name" value="{{ old('company_name') }}" required>
                                @error('company_name')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="form-group my-3">
                                <label for="description">انواع المنتجات المطلوبة	</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" required>{{ old('description') }}</textarea>
                                @error('description')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-success">اضافة</button>
                        </form>
                    </div>

                </div><!-- end cardbody -->
            </div><!-- end card -->
        </div> <!-- end col -->
    </div>

@endsection
