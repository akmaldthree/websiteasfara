@php($imageGuide = config('image_uploads.'.($preset ?? 'general')))
@if($imageGuide)
    <small class="image-upload-guideline">
        <strong>Rekomendasi: {{ $imageGuide['size'] }}</strong>
        <span>{{ $imageGuide['detail'] }}</span>
    </small>
@endif
