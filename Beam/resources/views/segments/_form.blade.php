<iframe id="outside" height="100%" width="100%" frameborder="0" marginwidth="0" marginheight="0"  src="{{ route('segments.embed', ['segmentId' => ($segment->id ?? null)]) }}"></iframe>

<script type="text/javascript">
    $(function() {
        iFrameResize({ log: false, heightCalculationMethod: 'max' }, '#outside');
    });
</script>