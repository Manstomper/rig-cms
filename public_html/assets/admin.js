/*! rigCMS */

function mceInit() {
	tinymce.init({
		selector: "[data-codeallowed='1']",
		height: 520,
		plugins: "code fullscreen hr image link table",
		skin: "rig",
		schema: "html5",
		element_format: "html",
		entity_encoding: "raw",
		valid_elements: "*[*]",
		extended_valid_elements: "*[*]",
		browser_spellcheck: true,
		convert_urls: false,
		end_container_on_empty_block: true,
		toolbar1: "styleselect removeformat | link unlink image bullist numlist table hr | togglemenu code fullscreen",
		setup: function(editor) {
			editor.addButton("togglemenu", {
				text: "",
				icon: "menu",
				type: "button",
				tooltip: "Show menu bar",
				onclick: function(e) {
					var menu = document.querySelector(".mce-menubar.mce-stack-layout-item"),
							button = e.target;
					while (button && button.parentNode && !button.className.match("mce-btn")) {
						button = button.parentNode;
					}
					if (menu.style.display !== "block") {
						menu.style.display = "block";
						button.className += " mce-active";
					}
					else {
						menu.style.display = "none";
						button.className = button.className.replace(" mce-active", "");
					}
				}
			});
		},
		init_instance_callback: function() {
			var textarea = document.querySelector("#articleForm textarea[name=body]");
			if (textarea && textarea.getAttribute("aria-invalid")) {
				document.querySelector("#articleForm .mce-tinymce").setAttribute("invalid", "1");
			}
		}
	});
}

function codeMirrorInit() {
	"use strict";

	var el = document.querySelectorAll("[data-codeallowed='1']");

	for (var i = el.length - 1; i >= 0; i -= 1) {
		el[i].removeAttribute("required");
		CodeMirror.fromTextArea(el[i], {
			smartIndent: false,
			indentWithTabs: true,
			lineWrapping: true,
			dragDrop: false,
			cursorBlinkRate: 0
		});
	}
}

$(document).ready(function() {
	"use strict";

	var forms = document.querySelectorAll("form");

	for (var i = forms.length - 1; i >= 0; i -= 1) {
		forms[i].reset();
	}

	if (typeof tinymce !== "undefined") {
		mceInit();
	}
	else if (typeof CodeMirror !== "undefined") {
		codeMirrorInit();
	}

	/*
	$("form[data-watch]").change(function(e) {
		e.target.form.setAttribute("data-isdirty", "1");
	});
	*/

	$(".multi-action").hide();
	$(".multi-action select[name='taxonomy']").hide();

	$(".multi-form")
		.on("submit", function(e) {
			if (confirm("Are you sure?") === false) {
				e.preventDefault();
			}
		})
		.on("change", function(e) {
			if (e.target.getAttribute("data-checkall")) {
				$(e.target).parents(".multi-form").find(".checkbox").prop("checked", e.target.checked);
			}
			if (e.target.className.indexOf("checkbox") !== -1) {
				var multiForm = $(e.target).parents(".multi-form");
				if (multiForm.find(".checkbox:checked").length > 0) {
					multiForm.find(".multi-action").show();
				}
				else {
					multiForm.find(".multi-action option:first-child").prop("selected", "selected");
					multiForm.find(".multi-action select[name='taxonomy']").hide();
					multiForm.find(".multi-action").hide();
				}
			}
			else if (e.target.value === "taxonomy-attach" || e.target.value === "taxonomy-detach") {
				$(".multi-action select[name='taxonomy']").show();
			}
			else if (e.target.name !== "taxonomy") {
				$(".multi-action select[name='taxonomy']").hide();
			}
		});

	$("#taxonomy-modal #taxonomy-form").submit(function(e) {
		e.preventDefault();
		var form = $(e.target);
		$.ajax({
			url: form.attr("action"),
			method: "POST",
			data: form.serialize(),
			dataType: "json",
			error: function() {
				alert("Oops!");
			},
			success: function(data) {
				var field = $("#taxonomy-field").html();
				field = field.replace("{{id}}", data.result.id);
				field = field.replace("{{name}}", data.result.name);
				$("#taxonomy-list > ul > li:last-child").after(field);
				$("#taxonomy-modal").modal("hide");
			}
		});
	});

	$("[data-template]").click(function(e) {
		var template = $(e.target.getAttribute("data-template")).html();
		var i = document.querySelector(e.target.getAttribute("data-appendto")).children.length + 1;
		template = template.replace(/{{i}}/g, i);
		$(e.target.getAttribute("data-appendto")).append(template);
	});

	$("#account-active").click(function() {
		$("#account-warning").toggle();
	});
});