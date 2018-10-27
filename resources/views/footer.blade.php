@foreach ($bodyScripts as $script)
    {!! Assets::script($script['src'] . Assets::getBuildVersion(), $script['attributes']) !!}
@endforeach