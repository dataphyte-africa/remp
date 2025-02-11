<style type="text/css">
@import url('../../../sass/transitions.scss');

.html-preview-link {
    text-decoration: none;
    overflow: hidden;
}

.html-preview-close.hidden {
    display: none;
}

.html-preview-box {
    color: white;
    white-space: pre-line;
    display: block;
    overflow: hidden;
    position: relative;
    text-align: center;
}

.html-preview-text {
    vertical-align: middle;
    padding: 5px 10px;
    display: inline-block;
    box-sizing: border-box;
    height: 100%;
    width: 100%;
    align-items: center;
}

.html-preview-close {
    position: absolute;
    display: block;
    top: 0;
    right: 0;
    text-decoration: none;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    min-width: 40px;
    height: 40px;
    letter-spacing: 0.05em;
    line-height: 40px;
    padding-right: 3px;
    text-align: right;
}

a.html-preview-close::after {
    content: "\00a0\00d7\00a0";
    font-size: 24px;
    vertical-align: sub;
    font-weight: normal;
    line-height: 40px;
    display: inline-block;
}
</style>

<template>
    <a class="html-preview-link" v-bind:href="$parent.url" v-on="$parent.url ? { click: $parent.clicked } : {}"
       v-if="isVisible" v-bind:style="[
        linkStyles,
        _position,
        dimensionOptions[dimensions]
    ]">
        <transition appear v-bind:name="transition">
            <div class="html-preview-box sans-serif" v-bind:style="[
                boxStyles,
                dimensionOptions[dimensions],
                customBoxStyles
            ]">
                <a class="html-preview-close" title="Close banner" href="javascript://"
                   v-bind:class="[{hidden: !closeable}]" v-on:click.stop="$parent.closed"
                   v-bind:style="closeStyles"><small>{{ closeText }}</small></a>
                <div v-html="$parent.injectVars(text)" class="html-preview-text"
                     v-bind:style="[_textAlign, textStyles]"></div>
            </div>
        </transition>
    </a>
</template>

<script>
export default {
    name: 'html-preview',
    props: [
        "positionOptions",
        "dimensionOptions",
        "alignmentOptions",
        "textAlign",
        "transition",
        "position",
        "offsetVertical",
        "offsetHorizontal",
        "dimensions",
        "show",
        "textColor",
        "fontSize",
        "backgroundColor",
        "targetUrl",
        "closeable",
        "closeText",
        "text",
        "css",
        "js",
        "includes",
        "displayType",
        "forcedPosition",
        "uuid",
        "campaignUuid"
    ],
    data: function () {
        return {
            visible: true,
            closeTracked: false,
            clickTracked: false,
        }
    },
    mounted: function () {
        let styles = this.css ? this.css.replace(/\r?\n|\r/gm, " ") : '',
            head = document.head || document.getElementsByTagName('head')[0],
            style = document.createElement('style');

        head.appendChild(style);

        style.type = 'text/css';
        if (style.styleSheet) {
            // This is required for IE8 and below.
            style.styleSheet.cssText = this.$parent.injectVars(styles);
        } else {
            style.appendChild(document.createTextNode(this.$parent.injectVars(styles)));
        }
    },
    computed: {
        _textAlign: function () {
            return this.alignmentOptions[this.textAlign] ? this.alignmentOptions[this.textAlign].style : {};
        },
        _position: function () {
            if (!this.$parent.customPositioned()) {
                return {};
            }

            if (this.positionOptions[this.position]) {
                let styles = this.positionOptions[this.position].style;

                for (let ii in styles) {
                    styles[ii] = ((ii == 'top' || ii == 'bottom') ? this.offsetVertical : this.offsetHorizontal) + 'px'
                }

                return styles;
            }

            return {};
        },
        linkStyles: function () {
            let position, zIndex;
            if (this.displayType === 'overlay') {
                position = 'fixed';
                zIndex = 9999;
            } else {
                position = 'relative'
            }
            if (typeof this.forcedPosition !== 'undefined') {
                position = this.forcedPosition;
            }
            return {
                textDecoration: 'none',
                position: position,
                overflow: 'hidden',
                zIndex: zIndex,
            }
        },
        textStyles: function () {
            return {
                color: this.textColor,
                fontSize: this.fontSize + "px",
            }
        },
        boxStyles: function () {
            return {
                backgroundColor: this.backgroundColor,
            }
        },
        closeStyles: function () {
            return {
                color: this.textColor,
            }
        },
        customBoxStyles: function () {
            return {}
        },
        isVisible: function () {
            return this.show && this.visible;
        },
    },
}
</script>
