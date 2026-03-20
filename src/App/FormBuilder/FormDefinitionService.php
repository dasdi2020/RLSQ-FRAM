<?php

declare(strict_types=1);

namespace App\FormBuilder;

use RLSQ\Database\Connection;

/**
 * CRUD sur les définitions de formulaires, champs et soumissions.
 */
class FormDefinitionService
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    // ==================== FORMS ====================

    public function createForm(array $data): array
    {
        $name = $data['name'] ?? throw new \InvalidArgumentException('name requis.');
        $slug = $data['slug'] ?? $this->slugify($name);

        $this->connection->execute(
            'INSERT INTO form_definitions (name, slug, description, table_id, settings) VALUES (:n, :s, :d, :t, :st)',
            [
                'n' => $name, 's' => $slug,
                'd' => $data['description'] ?? null,
                't' => $data['table_id'] ?? null,
                'st' => json_encode($data['settings'] ?? []),
            ],
        );

        return $this->getForm((int) $this->connection->lastInsertId());
    }

    public function getForm(int $id): ?array
    {
        $form = $this->connection->fetchOne('SELECT * FROM form_definitions WHERE id = :id', ['id' => $id]);
        if (!$form) {
            return null;
        }

        $form['fields'] = $this->getFields($id);
        $form['settings'] = json_decode($form['settings'] ?? '{}', true);

        return $form;
    }

    public function getFormBySlug(string $slug): ?array
    {
        $form = $this->connection->fetchOne('SELECT * FROM form_definitions WHERE slug = :s', ['s' => $slug]);
        if (!$form) {
            return null;
        }

        $form['fields'] = $this->getFields((int) $form['id']);
        $form['settings'] = json_decode($form['settings'] ?? '{}', true);

        return $form;
    }

    /** @return array[] */
    public function getAllForms(): array
    {
        $forms = $this->connection->fetchAll('SELECT * FROM form_definitions ORDER BY name');

        foreach ($forms as &$f) {
            $f['fields'] = $this->getFields((int) $f['id']);
            $f['settings'] = json_decode($f['settings'] ?? '{}', true);
            $f['submission_count'] = (int) $this->connection->fetchColumn(
                'SELECT COUNT(*) FROM form_submissions WHERE form_id = :fid',
                ['fid' => $f['id']],
            );
        }

        return $forms;
    }

    public function updateForm(int $id, array $data): ?array
    {
        $sets = [];
        $params = ['id' => $id];
        $allowed = ['name', 'description', 'is_published'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "{$f} = :{$f}";
                $params[$f] = $data[$f];
            }
        }

        if (array_key_exists('settings', $data)) {
            $sets[] = 'settings = :settings';
            $params['settings'] = json_encode($data['settings']);
        }

        if (array_key_exists('table_id', $data)) {
            $sets[] = 'table_id = :table_id';
            $params['table_id'] = $data['table_id'];
        }

        if (!empty($sets)) {
            $sets[] = 'updated_at = :now';
            $params['now'] = date('Y-m-d H:i:s');
            $this->connection->execute('UPDATE form_definitions SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
        }

        return $this->getForm($id);
    }

    public function deleteForm(int $id): void
    {
        $this->connection->execute('DELETE FROM form_definitions WHERE id = :id', ['id' => $id]);
    }

    // ==================== FIELDS ====================

    /** @return array[] */
    public function getFields(int $formId): array
    {
        $fields = $this->connection->fetchAll(
            'SELECT * FROM form_fields WHERE form_id = :fid ORDER BY sort_order, id',
            ['fid' => $formId],
        );

        foreach ($fields as &$f) {
            $f['visibility_rules'] = json_decode($f['visibility_rules'] ?? '{}', true);
            $f['validation'] = json_decode($f['validation'] ?? '{}', true);
            $f['options'] = json_decode($f['options'] ?? '{}', true);
        }

        return $fields;
    }

    public function addField(int $formId, array $data): array
    {
        $name = $data['name'] ?? throw new \InvalidArgumentException('name requis.');

        $this->connection->execute(
            'INSERT INTO form_fields (form_id, column_id, name, type, label, placeholder, help_text, default_value, is_required, is_visible, is_readonly, visibility_rules, validation, options, width, sort_order)
             VALUES (:fid, :cid, :n, :t, :l, :ph, :ht, :dv, :req, :vis, :ro, :vr, :val, :opt, :w, :so)',
            [
                'fid' => $formId,
                'cid' => $data['column_id'] ?? null,
                'n' => $name,
                't' => $data['type'] ?? 'text',
                'l' => $data['label'] ?? $name,
                'ph' => $data['placeholder'] ?? null,
                'ht' => $data['help_text'] ?? null,
                'dv' => $data['default_value'] ?? null,
                'req' => ($data['is_required'] ?? false) ? 1 : 0,
                'vis' => ($data['is_visible'] ?? true) ? 1 : 0,
                'ro' => ($data['is_readonly'] ?? false) ? 1 : 0,
                'vr' => json_encode($data['visibility_rules'] ?? []),
                'val' => json_encode($data['validation'] ?? []),
                'opt' => json_encode($data['options'] ?? []),
                'w' => $data['width'] ?? 12,
                'so' => $data['sort_order'] ?? 0,
            ],
        );

        $id = (int) $this->connection->lastInsertId();
        $f = $this->connection->fetchOne('SELECT * FROM form_fields WHERE id = :id', ['id' => $id]);
        $f['visibility_rules'] = json_decode($f['visibility_rules'] ?? '{}', true);
        $f['validation'] = json_decode($f['validation'] ?? '{}', true);
        $f['options'] = json_decode($f['options'] ?? '{}', true);

        return $f;
    }

    public function updateField(int $fieldId, array $data): ?array
    {
        $sets = [];
        $params = ['id' => $fieldId];
        $allowed = ['column_id', 'type', 'label', 'placeholder', 'help_text', 'default_value', 'is_required', 'is_visible', 'is_readonly', 'width', 'sort_order'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $val = $data[$f];
                if (in_array($f, ['is_required', 'is_visible', 'is_readonly'], true)) {
                    $val = $val ? 1 : 0;
                }
                $sets[] = "{$f} = :{$f}";
                $params[$f] = $val;
            }
        }

        foreach (['visibility_rules', 'validation', 'options'] as $jsonField) {
            if (array_key_exists($jsonField, $data)) {
                $sets[] = "{$jsonField} = :{$jsonField}";
                $params[$jsonField] = json_encode($data[$jsonField]);
            }
        }

        if (!empty($sets)) {
            $sets[] = 'updated_at = :now';
            $params['now'] = date('Y-m-d H:i:s');
            $this->connection->execute('UPDATE form_fields SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
        }

        $f = $this->connection->fetchOne('SELECT * FROM form_fields WHERE id = :id', ['id' => $fieldId]);
        if (!$f) {
            return null;
        }

        $f['visibility_rules'] = json_decode($f['visibility_rules'] ?? '{}', true);
        $f['validation'] = json_decode($f['validation'] ?? '{}', true);
        $f['options'] = json_decode($f['options'] ?? '{}', true);

        return $f;
    }

    public function deleteField(int $fieldId): void
    {
        $this->connection->execute('DELETE FROM form_fields WHERE id = :id', ['id' => $fieldId]);
    }

    /**
     * Reorder fields en batch.
     */
    public function reorderFields(int $formId, array $order): void
    {
        foreach ($order as $i => $fieldId) {
            $this->connection->execute(
                'UPDATE form_fields SET sort_order = :so WHERE id = :id AND form_id = :fid',
                ['so' => $i, 'id' => $fieldId, 'fid' => $formId],
            );
        }
    }

    // ==================== SUBMISSIONS ====================

    /**
     * Soumet un formulaire avec validation.
     */
    public function submit(int $formId, array $data, ?int $submittedBy = null, ?string $ip = null): array
    {
        $form = $this->getForm($formId);
        if (!$form) {
            throw new \RuntimeException('Formulaire introuvable.');
        }

        // Validation
        $errors = $this->validate($form['fields'], $data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Stocker la soumission
        $this->connection->execute(
            'INSERT INTO form_submissions (form_id, submitted_by, data, ip_address) VALUES (:fid, :uid, :d, :ip)',
            ['fid' => $formId, 'uid' => $submittedBy, 'd' => json_encode($data), 'ip' => $ip],
        );

        $submissionId = (int) $this->connection->lastInsertId();

        // Si le formulaire est lié à une table, insérer aussi dans cette table
        if ($form['table_id']) {
            $this->insertIntoLinkedTable($form, $data);
        }

        return [
            'success' => true,
            'submission_id' => $submissionId,
        ];
    }

    /**
     * @return array[]
     */
    public function getSubmissions(int $formId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $total = (int) $this->connection->fetchColumn('SELECT COUNT(*) FROM form_submissions WHERE form_id = :fid', ['fid' => $formId]);

        $rows = $this->connection->fetchAll(
            'SELECT * FROM form_submissions WHERE form_id = :fid ORDER BY submitted_at DESC LIMIT :lim OFFSET :off',
            ['fid' => $formId, 'lim' => $perPage, 'off' => $offset],
        );

        foreach ($rows as &$r) {
            $r['data'] = json_decode($r['data'] ?? '{}', true);
        }

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function getSubmission(int $id): ?array
    {
        $r = $this->connection->fetchOne('SELECT * FROM form_submissions WHERE id = :id', ['id' => $id]);
        if (!$r) {
            return null;
        }

        $r['data'] = json_decode($r['data'] ?? '{}', true);

        return $r;
    }

    /**
     * Génère le JSON de rendu pour le frontend.
     */
    public function renderForm(int $formId): ?array
    {
        $form = $this->getForm($formId);
        if (!$form) {
            return null;
        }

        return [
            'id' => (int) $form['id'],
            'name' => $form['name'],
            'slug' => $form['slug'],
            'description' => $form['description'],
            'settings' => $form['settings'],
            'fields' => array_map(fn ($f) => [
                'name' => $f['name'],
                'type' => $f['type'],
                'label' => $f['label'],
                'placeholder' => $f['placeholder'],
                'help_text' => $f['help_text'],
                'default_value' => $f['default_value'],
                'is_required' => (bool) $f['is_required'],
                'is_visible' => (bool) $f['is_visible'],
                'is_readonly' => (bool) $f['is_readonly'],
                'visibility_rules' => $f['visibility_rules'],
                'validation' => $f['validation'],
                'options' => $f['options'],
                'width' => (int) $f['width'],
            ], array_filter($form['fields'], fn ($f) => (bool) $f['is_visible'])),
        ];
    }

    // ==================== PRIVATE ====================

    private function validate(array $fields, array $data): array
    {
        $errors = [];

        foreach ($fields as $field) {
            $name = $field['name'];
            $value = $data[$name] ?? null;
            $label = $field['label'];

            if ($field['is_required'] && ($value === null || $value === '')) {
                $errors[$name][] = "Le champ \"{$label}\" est requis.";
            }

            $rules = $field['validation'] ?? [];

            if (!empty($rules['min_length']) && is_string($value) && mb_strlen($value) < $rules['min_length']) {
                $errors[$name][] = "{$label} : minimum {$rules['min_length']} caractères.";
            }
            if (!empty($rules['max_length']) && is_string($value) && mb_strlen($value) > $rules['max_length']) {
                $errors[$name][] = "{$label} : maximum {$rules['max_length']} caractères.";
            }
            if (!empty($rules['email']) && $value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$name][] = "{$label} : email invalide.";
            }
            if (!empty($rules['pattern']) && $value !== null && $value !== '' && !preg_match($rules['pattern'], (string) $value)) {
                $errors[$name][] = "{$label} : format invalide.";
            }
        }

        return $errors;
    }

    private function insertIntoLinkedTable(array $form, array $data): void
    {
        // Mapper les champs du formulaire vers les colonnes de la table
        $mapped = [];

        foreach ($form['fields'] as $field) {
            if ($field['column_id'] && isset($data[$field['name']])) {
                // Récupérer le nom de la colonne
                $col = $this->connection->fetchOne('SELECT name FROM _meta_columns WHERE id = :id', ['id' => $field['column_id']]);
                if ($col) {
                    $mapped[$col['name']] = $data[$field['name']];
                }
            }
        }

        if (empty($mapped)) {
            return;
        }

        // Récupérer le nom de la table
        $table = $this->connection->fetchOne('SELECT name FROM _meta_tables WHERE id = :id', ['id' => $form['table_id']]);
        if (!$table) {
            return;
        }

        $mapped['created_at'] = date('Y-m-d H:i:s');
        $mapped['updated_at'] = date('Y-m-d H:i:s');

        $cols = array_keys($mapped);
        $placeholders = array_map(fn ($c) => ':' . $c, $cols);

        $this->connection->execute(
            sprintf('INSERT INTO "%s" (%s) VALUES (%s)', $table['name'], implode(', ', $cols), implode(', ', $placeholders)),
            $mapped,
        );
    }

    private function slugify(string $t): string
    {
        if (function_exists('transliterator_transliterate')) {
            $t = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', trim($t));
        } else {
            $t = strtolower(trim($t));
        }

        return trim(preg_replace('/[^a-z0-9]+/', '-', $t), '-');
    }
}
