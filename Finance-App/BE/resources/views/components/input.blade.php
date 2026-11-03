@props(['label','name','type' => 'text','placeholder' => ''])
<div>
    <label for="{{$name}}" class="mb-5 block text-sm font-medium text-ink">{{$label}}</label>
    <input type="{{$type}}" name="{{$name}}" placeholder="{{$placeholder}}"
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-line bg-white px-3 py-2.5 text-sm text-ink placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20']) }}>
</div>