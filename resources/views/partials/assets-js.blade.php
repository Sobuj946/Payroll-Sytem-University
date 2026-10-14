@if (\App\Support\Assets::local())
    <script src="{{ asset('assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@else
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@endif
