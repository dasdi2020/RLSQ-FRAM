<script>
    import { onMount } from 'svelte';

    let { value = $bindable(''), language = 'javascript', theme = 'vs-dark', readOnly = false, onChange = null } = $props();

    let container;
    let editor;
    let monacoModule;

    onMount(async () => {
        // Lazy load Monaco
        monacoModule = await import('monaco-editor');

        // Configure workers via blob URLs (no separate worker files needed)
        self.MonacoEnvironment = {
            getWorker(_, label) {
                const workerCode = 'self.onmessage = function() {}';
                const blob = new Blob([workerCode], { type: 'application/javascript' });
                return new Worker(URL.createObjectURL(blob));
            }
        };

        editor = monacoModule.editor.create(container, {
            value,
            language,
            theme,
            readOnly,
            automaticLayout: true,
            minimap: { enabled: true },
            fontSize: 14,
            fontFamily: "'Fira Code', 'Cascadia Code', 'JetBrains Mono', monospace",
            fontLigatures: true,
            lineNumbers: 'on',
            scrollBeyondLastLine: false,
            wordWrap: 'on',
            tabSize: 4,
            insertSpaces: true,
            renderWhitespace: 'selection',
            bracketPairColorization: { enabled: true },
            suggest: { showWords: true },
            padding: { top: 8 },
        });

        editor.onDidChangeModelContent(() => {
            const v = editor.getValue();
            value = v;
            if (onChange) onChange(v);
        });

        return () => { editor?.dispose(); };
    });

    // Update content when value changes externally
    $effect(() => {
        if (editor && editor.getValue() !== value) {
            editor.setValue(value || '');
        }
    });

    // Update language when it changes
    $effect(() => {
        if (editor && monacoModule) {
            const model = editor.getModel();
            if (model) {
                monacoModule.editor.setModelLanguage(model, language);
            }
        }
    });
</script>

<div bind:this={container} class="w-full h-full min-h-[200px]"></div>
