@foreach ($bodyScripts as $script)
    {!! Assets::getHtmlBuilder()->script($script['src'] . Assets::getBuildVersion(), $script['attributes']) !!}
@endforeach