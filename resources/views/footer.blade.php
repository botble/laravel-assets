@foreach ($bodyScripts as $script)
    {!! app('html')->script($script['src'], $script['attributes']) !!}
@endforeach