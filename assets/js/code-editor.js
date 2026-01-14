/**
 * Monaco Editor Integration
 */
(function($) {
	'use strict';

	// Initialize Monaco Editor when available
	window.initMonacoEditor = function(containerId, textareaId, language) {
		const container = document.getElementById(containerId);
		const textarea = document.getElementById(textareaId);

		if (!container || ! textarea || typeof monaco === 'undefined') {
			console.error('Monaco Editor not available or elements not found');
			return;
		}

		// Configure Monaco loader
		require.config({
			paths: {
				'vs': 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs'
			}
		});

		require(['vs/editor/editor.main'], function() {
			// Register custom snippets for WordPress
			if (language === 'php') {
				monaco.languages.registerCompletionItemProvider('php', {
					provideCompletionItems: function() {
						const suggestions = [
							{
								label: 'wp_response',
								kind: monaco.languages.CompletionItemKind.Snippet,
								insertText: 'return new WP_REST_Response( ${1:array()}, ${2:200} );',
								insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
								documentation: 'Return a standard WP_REST_Response'
							},
							{
								label: 'get_users',
								kind: monaco.languages.CompletionItemKind.Snippet,
								insertText: '$users = get_users();',
								documentation: 'Get WordPress users list'
							},
							{
								label: 'get_option',
								kind: monaco.languages.CompletionItemKind.Snippet,
								insertText: '$value = get_option( "${1:option_name}", ${2:false} );',
								insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
								documentation: 'Retrieve a WordPress option'
							},
							{
								label: 'update_option',
								kind: monaco.languages.CompletionItemKind.Snippet,
								insertText: 'update_option( "${1:option_name}", ${2:$value} );',
								insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
								documentation: 'Update a WordPress option'
							},
							{
								label: 'rem_log',
								kind: monaco.languages.CompletionItemKind.Snippet,
								insertText: 'do_action( "wp_rem_ingest_received", $webhook_id, $mapped_data, $raw_data );',
								documentation: 'Trigger plugin ingest hook'
							},
							{
								label: 'php_tag',
								kind: monaco.languages.CompletionItemKind.Snippet,
								insertText: '<?php\n\n$0',
								insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
								documentation: 'PHP opening tag'
							}
						];
						return { suggestions: suggestions };
					}
				});
			}

			// Create editor
			const editor = monaco.editor.create(container, {
				value: textarea.value,
				language: language,
				theme: 'vs-dark',
				automaticLayout: true,
				minimap: { enabled: false },
				lineNumbers: 'on',
				wordWrap: 'on',
				scrollBeyondLastLine: false,
				fontSize: 14
			});

			// Sync editor content with textarea
			editor.onDidChangeModelContent(function() {
				textarea.value = editor.getValue();
			});

			// Store editor instance
			window.monacoEditors = window.monacoEditors || {};
			window.monacoEditors[textareaId] = editor;
		});
	};

})(jQuery);
