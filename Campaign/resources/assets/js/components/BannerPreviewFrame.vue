<style>
    #bannerPreviewFrameWrap {
        background: white;
    }

    #iframe-preview {
        width: 100%;
        height: 100%;
        border: 0;
    }
</style>

<template>
    <div style="height: 100%" ref="bannerPreviewFrameRef" id="bannerPreviewFrameWrap"></div>
</template>

<script>
import Vue from "vue";
import BannerPreview from "./BannerPreview.vue";

export default {
    props: BannerPreview.props,
    mounted: function() {
        let iframe = document.createElement("iframe");
        iframe.setAttribute("id", "iframe-preview");
        this.$refs.bannerPreviewFrameRef.appendChild(iframe)

        // create iframe body
        // preview does not display in Firefox only blinks and disappears without next lines of code
        iframe.contentWindow.document.open();
        iframe.contentWindow.document.close();

        // copy styles to iframe
        let styles = document.querySelectorAll("head style")
        styles.forEach(li => {
            iframe.contentWindow.document.head.append(li.cloneNode(true))
        });

        return new Vue({
            el: iframe.contentWindow.document.body,
            render: h => h(BannerPreview, {
                props: this.$props,
            }),
        });
    },
};
</script>
