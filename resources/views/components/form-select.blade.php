@props(['name', 'label', 'options', 'value' => null, 'required' => false, 'placeholder' => 'Select...', 'col' => 6])
<div class="col-md-{{ $col }}">
    <label for="{{ $name }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
    <select id="{{ $name }}" name="{{ $name }}" class="form-select @error($name) is-invalid @enderror" @required($required)>
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>
    @error($name) <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
