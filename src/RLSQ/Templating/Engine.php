<?php

declare(strict_types=1);

namespace RLSQ\Templating;

use RLSQ\Templating\Loader\FilesystemLoader;
use RLSQ\Templating\Node\BlockNode;
use RLSQ\Templating\Node\ExtendsNode;

class Engine implements EngineInterface
{
    private Lexer $lexer;
    private Parser $parser;
    private Compiler $compiler;
    private ?string $cacheDir;

    /** @var array<string, string> Cache du code compilé en mémoire */
    private array $compiledCache = [];

    public function __construct(
        private readonly FilesystemLoader $loader,
        ?string $cacheDir = null,
    ) {
        $this->lexer = new Lexer();
        $this->parser = new Parser();
        $this->compiler = new Compiler();
        $this->cacheDir = $cacheDir;
    }

    public function render(string $name, array $parameters = []): string
    {
        $compiled = $this->compileTemplate($name);

        return $this->execute($compiled, $parameters, $name);
    }

    public function exists(string $name): bool
    {
        return $this->loader->exists($name);
    }

    /**
     * Compile un template en code PHP (avec cache disque optionnel).
     */
    private function compileTemplate(string $name): string
    {
        // Cache mémoire
        if (isset($this->compiledCache[$name])) {
            return $this->compiledCache[$name];
        }

        // Cache disque
        if ($this->cacheDir !== null) {
            $cachePath = $this->cacheDir . '/' . md5($name) . '.php';
            $sourcePath = $this->loader->find($name);

            if (file_exists($cachePath) && filemtime($cachePath) >= filemtime($sourcePath)) {
                $code = file_get_contents($cachePath);
                $this->compiledCache[$name] = $code;
                return $code;
            }
        }

        $source = $this->loader->getSource($name);
        $tokens = $this->lexer->tokenize($source);
        $ast = $this->parser->parse($tokens);

        // Gérer l'héritage (extends)
        $parentName = null;
        $filteredAst = [];

        foreach ($ast as $node) {
            if ($node instanceof ExtendsNode) {
                $parentName = $node->parent;
            } else {
                $filteredAst[] = $node;
            }
        }

        if ($parentName !== null) {
            // Compiler les blocs enfants pour la collecte
            $childCode = $this->compiler->compile($filteredAst);
            // Compiler le parent
            $parentCode = $this->compileTemplate($parentName);

            // Le code enfant définit les blocs (override), puis on exécute le parent
            $code = $childCode . "\n" . $parentCode;
        } else {
            $code = $this->compiler->compile($filteredAst);
        }

        $this->compiledCache[$name] = $code;

        // Écrire cache disque
        if ($this->cacheDir !== null) {
            if (!is_dir($this->cacheDir)) {
                mkdir($this->cacheDir, 0777, true);
            }
            file_put_contents($cachePath, $code, LOCK_EX);
        }

        return $code;
    }

    /**
     * Exécute le code PHP compilé avec les paramètres.
     */
    private function execute(string $code, array $parameters, string $templateName): string
    {
        // $__context est partagé entre les blocs pour l'héritage
        $__context = $parameters;
        $__engine = $this;
        $__blocks = [];

        extract($parameters);

        ob_start();

        try {
            eval($code);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new \RuntimeException(sprintf(
                'Erreur dans le template "%s" : %s',
                $templateName,
                $e->getMessage(),
            ), 0, $e);
        }

        return ob_get_clean();
    }
}
