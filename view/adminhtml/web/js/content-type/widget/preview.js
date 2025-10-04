/*eslint-disable */
/* jscs:disable */

function _inheritsLoose(subClass, superClass) { subClass.prototype = Object.create(superClass.prototype); subClass.prototype.constructor = subClass; _setPrototypeOf(subClass, superClass); }

function _setPrototypeOf(o, p) { _setPrototypeOf = Object.setPrototypeOf || function _setPrototypeOf(o, p) { o.__proto__ = p; return o; }; return _setPrototypeOf(o, p); }

define([
    "jquery",
    "knockout",
    "mage/translate",
    "Magento_PageBuilder/js/widget-initializer",
    "mageUtils", "underscore",
    "Magento_PageBuilder/js/config",
    "Magento_PageBuilder/js/content-type-menu/hide-show-option",
    "Magento_PageBuilder/js/content-type/style-registry",
    "Magento_PageBuilder/js/utils/object",
    "Magento_PageBuilder/js/content-type/preview"
], function (
    _jquery,
    _knockout,
    _translate,
    _widgetInitializer,
    _mageUtils,
    _underscore,
    _config,
    _hideShowOption,
    _styleRegistry,
    _object,
    _preview
) {
    /**
     * Copyright © Magento, Inc. All rights reserved.
     * See COPYING.txt for license details.
     */

    /**
     * @api
     */
    var Preview = /*#__PURE__*/function (_preview2) {
        "use strict";

        _inheritsLoose(Preview, _preview2);

        /**
         * @inheritdoc
         */
        function Preview(contentType, config, observableUpdater) {
            var _this;

            _this = _preview2.call(this, contentType, config, observableUpdater) || this;
            _this.displayingBlockPreview = _knockout.observable(false);
            _this.loading = _knockout.observable(false);
            _this.messages = {
                NOT_SELECTED: (0, _translate)("Widget preview not available"),
                UNKNOWN_ERROR: (0, _translate)("An unknown error occurred. Please try again.")
            };
            _this.placeholderText = _knockout.observable(_this.messages.NOT_SELECTED);
            return _this;
        }
        /**
         * Return an array of options
         *
         * @returns {OptionsInterface}
         */


        var _proto = Preview.prototype;

        _proto.retrieveOptions = function retrieveOptions() {
            var options = _preview2.prototype.retrieveOptions.call(this);

            options.hideShow = new _hideShowOption({
                preview: this,
                icon: _hideShowOption.showIcon,
                title: _hideShowOption.showText,
                action: this.onOptionVisibilityToggle,
                classes: ["hide-show-content-type"],
                sort: 40
            });
            return options;
        }
        /**
         * Runs the widget initializer for each configured widget
         */
        ;

        _proto.initializeWidgets = function initializeWidgets(element) {
            if (element) {
                this.element = element;
                (0, _widgetInitializer)({
                    config: _config.getConfig("widgets"),
                    breakpoints: _config.getConfig("breakpoints"),
                    currentViewport: _config.getConfig("viewport")
                }, element);
            }
        }
        /**
         * Updates the view state using the data provided
         * @param {DataObject} data
         */
        ;

        _proto.processBlockData = function processBlockData(data) {
            // Only load if something changed
            if (data.content) {
                if (typeof data.content_settings !== "undefined" && data.content_settings !== "") {
                    let parsedData = JSON.parse(data.content_settings);
                    if (parsedData.preview && parsedData.preview !== "") {
                        this.processRequest(parsedData, "preview");
                    } else {
                        this.data.main.html(parsedData.preview);
                        this.showBlockPreview(false);
                        this.placeholderText(this.messages.NOT_SELECTED);
                    }
                } else {
                    this.data.main.html("");
                    this.showBlockPreview(false);
                    this.placeholderText(this.messages.NOT_SELECTED);
                }
            }
        }
        /**
         * @inheritdoc
         */
        ;

        _proto.afterObservablesUpdated = function afterObservablesUpdated() {
            _preview2.prototype.afterObservablesUpdated.call(this);

            var data = this.contentType.dataStore.getState(); // Only load if something changed

            this.processBlockData(data);
        }
        /**
         * Display preview placeholder
         *
         * @param {DataObject} data
         * @param {string} identifierName
         */
        ;

        _proto.displayPreviewPlaceholder = function displayPreviewPlaceholder(data, identifierName) {
            var blockId = (0, _object.get)(data, identifierName); // Only load if something changed

            if (this.lastBlockId === blockId && this.lastTemplate === data.template) {
                // The mass converter will have transformed the HTML property into a directive
                if (this.lastRenderedHtml) {
                    this.data.main.html(this.lastRenderedHtml);
                    this.showBlockPreview(true);
                    this.initializeWidgets(this.element);
                }
            } else {
                this.showBlockPreview(false);
                this.placeholderText("");
            }

            if (!blockId || blockId && blockId.toString().length === 0 || data.template.length === 0) {
                this.showBlockPreview(false);
                this.placeholderText(this.messages.NOT_SELECTED);
                return;
            }
        }
        /**
         *
         * @param {DataObject} data
         * @param {string} contentPreview
         */
        ;

        _proto.processRequest = function processRequest(data, contentPreview) {
            var _this2 = this;
            var contentPreview = (0, _object.get)(data, contentPreview);
            _this2.data.main.html(contentPreview);
            _this2.showBlockPreview(true);
        }
        /**
         * Toggle display of block preview.  If showing block preview, add hidden mode to PB preview.
         * @param {boolean} isShow
         */
        ;

        _proto.showBlockPreview = function showBlockPreview(isShow) {
            this.displayingBlockPreview(isShow);
        }
        /**
         * Adapt content to view it on stage.
         *
         * @param content
         */
        ;

        _proto.processContent = function processContent(content) {
            var processedContent = this.processBackgroundImages(content);
            processedContent = this.processBreakpointStyles(processedContent);
            return processedContent;
        }
        /**
         * Generate styles for background images.
         *
         * @param {string} content
         * @return string
         */
        ;

        _proto.processBackgroundImages = function processBackgroundImages(content) {
            var document = new DOMParser().parseFromString(content, "text/html");
            var elements = document.querySelectorAll("[data-background-images]");
            var styleBlock = document.createElement("style");

            var viewports = _config.getConfig("viewports");

            elements.forEach(function (element) {
                var rawAttrValue = element.getAttribute("data-background-images").replace(/\\(.)/mg, "$1");
                var attrValue = JSON.parse(rawAttrValue);

                var elementClass = "background-image-" + _mageUtils.uniqueid(13);

                var rules = "";
                Object.keys(attrValue).forEach(function (imageName) {
                    var imageUrl = attrValue[imageName];
                    var viewportName = imageName.replace("_image", "");

                    if (viewports[viewportName].stage && imageUrl) {
                        rules += "." + viewportName + "-viewport ." + elementClass + " {\n                            background-image: url(\"" + imageUrl + "\");\n                        }";
                    }
                });

                if (rules.length) {
                    styleBlock.append(rules);
                    element.classList.add(elementClass);
                }
            });

            if (elements.length && styleBlock.innerText.length) {
                document.body.append(styleBlock);
                content = document.head.innerHTML + document.body.innerHTML;
            }

            return content;
        }
        /**
         * Replace media queries with viewport classes.
         *
         * @param {string} content
         * @return string
         */
        ;

        _proto.processBreakpointStyles = function processBreakpointStyles(content) {
            var document = new DOMParser().parseFromString(content, "text/html");
            var styleBlocks = document.querySelectorAll("style");
            var mediaStyleBlock = document.createElement("style");

            var viewports = _config.getConfig("viewports");

            styleBlocks.forEach(function (styleBlock) {
                var cssRules = styleBlock.sheet.cssRules;
                Array.from(cssRules).forEach(function (rule) {
                    var mediaScope = rule instanceof CSSMediaRule && _underscore.findKey(viewports, function (viewport) {
                        return rule.conditionText === viewport.media;
                    });

                    if (mediaScope) {
                        Array.from(rule.cssRules).forEach(function (mediaRule, index) {
                            if (mediaRule.selectorText.indexOf(_styleRegistry.pbStyleAttribute) !== -1) {
                                var searchPattern = new RegExp(_config.getConfig("bodyId") + " ", "g");
                                var replaceValue = _config.getConfig("bodyId") + " ." + mediaScope + "-viewport ";
                                var selector = mediaRule.selectorText.replace(searchPattern, replaceValue);
                                mediaStyleBlock.append(selector + " {" + mediaRule.style.cssText + "}");
                            }
                        });
                    }
                });
            });

            if (mediaStyleBlock.innerText.length) {
                document.body.append(mediaStyleBlock);
                content = document.head.innerHTML + document.body.innerHTML;
            }

            return content;
        };

        return Preview;
    }(_preview);

    return Preview;
});
//# sourceMappingURL=preview.js.map
