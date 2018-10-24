@foreach ($stylesheets as $style)
    {!! app('html')->style($style['src'], $style['attributes']) !!}
@endforeach

@foreach ($headScripts as $script)
    {!! app('html')->script($script['src'], $script['attributes']) !!}
    @if (!empty($script['fallback']))
        <script>window.{!! $script['fallback'] !!} || document.write('<script src="{{ $script['fallbackURL'] }}"><\/script>')</script>
    @endif
@endforeach