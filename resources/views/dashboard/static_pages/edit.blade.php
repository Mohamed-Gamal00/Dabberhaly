@extends('dashboard.index')

@section('title', 'تعديل صفحة الموقع')

@section('breadcrumb')
    @parent
    <li class="breadcrumb-item"><a href="{{ route('pages.index') }}">صفحات الموقع</a></li>
    <li class="breadcrumb-item active" aria-current="page">تعديل</li>
@endsection

@section('section')

    <!-- end page title -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    {{-- Form Start --}}
                    <div class="container">
                        <form action="{{ route('pages.update', $page->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            <div class="form-group my-3">
                                <label for="title">العنوان</label>
                                <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" id="title"
                                    value="{{ old('title', $page->title) }}" required>
                                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group my-3">
                                <label for="title_en">العنوان بالإنجليزية</label>
                                <input type="text" name="title_en" class="form-control @error('title_en') is-invalid @enderror" id="title_en"
                                    value="{{ old('title_en', $page->title_en) }}" dir="ltr" style="text-align:left">
                                @error('title_en')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group my-3">
                                <label for="content">المحتوي</label>
                                {{-- <textarea name="content" class="form-control text-editor" id="content" required>{{ $page->content }}</textarea> --}}
                                <textarea id="elm1" name="content" class="@error('content') is-invalid @enderror">{!! old('content', $page->content) !!}</textarea>
                                @error('content')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group my-3">
                                <label for="content_en">المحتوي بالإنجليزية</label>
                                <textarea id="elm2" name="content_en" class="@error('content_en') is-invalid @enderror">{!! old('content_en', $page->content_en) !!}</textarea>
                                @error('content_en')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn btn-primary">تحديث</button>
                        </form>
                    </div>
                @endsection

                @section('scripts')
                    <!--tinymce js-->
                    <script src="{{ asset('assets/libs/tinymce/tinymce.min.js') }}"></script>

                    <!-- init js -->
                    <script src="{{ asset('assets/js/pages/form-editor.init.js') }}"></script>
                    {{-- <script src="https://cdn.ckeditor.com/ckeditor5/35.3.2/classic/ckeditor.js"></script>
                    <script>
                        ClassicEditor.create(document.querySelector('.text-editor')).catch(error => {
                            console.error(error);
                        });
                    </script> --}}

                </div><!-- end cardbody -->
            </div><!-- end card -->
        </div> <!-- end col -->
    </div>


    {{-- Form End --}}

@endsection
