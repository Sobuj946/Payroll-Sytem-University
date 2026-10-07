@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null, 'col' => 6])
<div class="col-md-{{ $col }}">
    <label for="{{ $name }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
    <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}"
           @if ($type !== 'file') value="{{ old($name, $value) }}" @endif
           class="form-control @error($name) is-invalid @enderror" @required($required) {{ $attributes }}>
    @error($name) <div class="invalid-feedback">{{ $message }}</div> @enderror
    @if ($hint) <div class="form-text">{{ $hint }}</div> @endif
</div>
