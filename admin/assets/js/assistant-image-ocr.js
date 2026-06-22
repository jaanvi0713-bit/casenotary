/**
 * Browser-side OCR for screenshots and photos.
 */
(function () {
    "use strict";

    var MAX_WIDTH = 1800;
    var MAX_TEXT = 50000;

    function isImageFile(file) {
        if (!file) {
            return false;
        }
        var name = (file.name || "").toLowerCase();
        var type = (file.type || "").toLowerCase();
        return /^image\//.test(type) || /\.(jpe?g|png|gif|webp|bmp)$/i.test(name);
    }

    function resizeForOcr(file) {
        return new Promise(function (resolve, reject) {
            if (!window.createImageBitmap && !window.Image) {
                resolve(file);
                return;
            }

            var url = URL.createObjectURL(file);
            var img = new Image();

            img.onload = function () {
                URL.revokeObjectURL(url);
                var width = img.naturalWidth || img.width;
                var height = img.naturalHeight || img.height;

                if (!width || !height || width <= MAX_WIDTH) {
                    resolve(file);
                    return;
                }

                var scale = MAX_WIDTH / width;
                var canvas = document.createElement("canvas");
                canvas.width = MAX_WIDTH;
                canvas.height = Math.max(1, Math.round(height * scale));
                var ctx = canvas.getContext("2d");
                if (!ctx) {
                    resolve(file);
                    return;
                }

                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                canvas.toBlob(function (blob) {
                    if (!blob) {
                        resolve(file);
                        return;
                    }
                    resolve(new File([blob], file.name, { type: "image/png" }));
                }, "image/png", 0.9);
            };

            img.onerror = function () {
                URL.revokeObjectURL(url);
                resolve(file);
            };

            img.src = url;
        });
    }

    function ocrOptions() {
        var options = { logger: function () {} };
        if (window.ASSISTANT_TESSERACT_WORKER) {
            options.workerPath = window.ASSISTANT_TESSERACT_WORKER;
            options.workerBlobURL = false;
        }
        if (window.ASSISTANT_TESSERACT_CORE) {
            options.corePath = window.ASSISTANT_TESSERACT_CORE;
        }
        return options;
    }

    function prepare(file) {
        if (typeof Tesseract === "undefined") {
            return Promise.reject(new Error("Image reader is not loaded. Refresh the page and try again."));
        }

        return resizeForOcr(file).then(function (optimized) {
            return Tesseract.recognize(optimized, "eng", ocrOptions());
        }).then(function (result) {
            var text = String(result && result.data && result.data.text ? result.data.text : "")
                .replace(/\r/g, "\n")
                .replace(/[ \t]+\n/g, "\n")
                .replace(/\n{3,}/g, "\n\n")
                .trim();

            if (text.length < 8) {
                return Promise.reject(new Error("Could not read enough text from this image. Try a clearer screenshot."));
            }

            return {
                documentText: text.slice(0, MAX_TEXT),
                file: null,
                label: file.name + " (screenshot)",
                fromScreenshot: true
            };
        });
    }

    window.AssistantImageOcr = {
        isImageFile: isImageFile,
        prepare: prepare
    };
})();
