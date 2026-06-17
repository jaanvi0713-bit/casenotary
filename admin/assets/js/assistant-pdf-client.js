/**
 * Browser-side PDF preparation for the AI assistant (no server Poppler).
 */
(function () {
    "use strict";

    var MAX_PAGES_TEXT = 8;
    var MIN_TEXT_CHARS = 60;

    function isPdfFile(file) {
        if (!file) {
            return false;
        }
        var name = (file.name || "").toLowerCase();
        var type = (file.type || "").toLowerCase();
        return name.endsWith(".pdf") || type === "application/pdf";
    }

    function ensurePdfJs() {
        if (typeof pdfjsLib === "undefined") {
            return Promise.reject(new Error("PDF reader is not loaded. Refresh the page and try again."));
        }
        if (!pdfjsLib.GlobalWorkerOptions.workerSrc) {
            if (window.ASSISTANT_PDF_WORKER) {
                pdfjsLib.GlobalWorkerOptions.workerSrc = window.ASSISTANT_PDF_WORKER;
            } else {
                return Promise.reject(new Error("PDF worker is not configured. Refresh the page and try again."));
            }
        }
        return Promise.resolve();
    }

    function extractTextFromPdf(pdf) {
        var pages = Math.min(pdf.numPages || 0, MAX_PAGES_TEXT);
        var parts = [];
        var chain = Promise.resolve();

        for (var pageNum = 1; pageNum <= pages; pageNum++) {
            chain = chain.then(function (num) {
                return pdf.getPage(num).then(function (page) {
                    return page.getTextContent().then(function (content) {
                        var text = content.items
                            .map(function (item) {
                                return item.str || "";
                            })
                            .join(" ")
                            .replace(/\s+/g, " ")
                            .trim();
                        if (text) {
                            parts.push(text);
                        }
                    });
                });
            }.bind(null, pageNum));
        }

        return chain.then(function () {
            return parts.join("\n\n").trim();
        });
    }

    function prepare(file) {
        return ensurePdfJs().then(function () {
            return file.arrayBuffer();
        }).then(function (buffer) {
            return pdfjsLib.getDocument({ data: buffer }).promise;
        }).then(function (pdf) {
            return extractTextFromPdf(pdf).then(function (text) {
                var trimmed = text.replace(/\s+/g, " ").trim();

                return {
                    documentText: trimmed ? trimmed.slice(0, 50000) : "",
                    file: file,
                    label: trimmed.length >= MIN_TEXT_CHARS
                        ? file.name + " (text extracted)"
                        : file.name
                };
            });
        });
    }

    window.AssistantPdfClient = {
        isPdfFile: isPdfFile,
        prepare: prepare
    };
})();
