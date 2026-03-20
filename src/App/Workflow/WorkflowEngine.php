<?php

declare(strict_types=1);

namespace App\Workflow;

use RLSQ\Database\Connection;

class WorkflowEngine
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        $this->ensureTable();
    }

    public function createWorkflow(array $data): array
    {
        $this->connection->execute(
            'INSERT INTO workflows (name, trigger_type, trigger_config, is_active) VALUES (:n, :tt, :tc, :a)',
            ['n' => $data['name'] ?? '', 'tt' => $data['trigger_type'] ?? 'manual', 'tc' => json_encode($data['trigger_config'] ?? []), 'a' => ($data['is_active'] ?? true) ? 1 : 0],
        );
        $id = (int) $this->connection->lastInsertId();
        return $this->getWorkflow($id);
    }

    public function getWorkflow(int $id): ?array
    {
        $w = $this->connection->fetchOne('SELECT * FROM workflows WHERE id = :id', ['id' => $id]);
        if (!$w) { return null; }
        $w['trigger_config'] = json_decode($w['trigger_config'] ?? '{}', true);
        $w['steps'] = $this->getSteps($id);
        return $w;
    }

    /** @return array[] */
    public function getAllWorkflows(): array
    {
        $ws = $this->connection->fetchAll('SELECT * FROM workflows ORDER BY name');
        foreach ($ws as &$w) {
            $w['trigger_config'] = json_decode($w['trigger_config'] ?? '{}', true);
            $w['step_count'] = (int) $this->connection->fetchColumn('SELECT COUNT(*) FROM workflow_steps WHERE workflow_id = :wid', ['wid' => $w['id']]);
        }
        return $ws;
    }

    public function deleteWorkflow(int $id): void
    {
        $this->connection->execute('DELETE FROM workflows WHERE id = :id', ['id' => $id]);
    }

    public function addStep(int $workflowId, array $data): array
    {
        $this->connection->execute(
            'INSERT INTO workflow_steps (workflow_id, type, config, position) VALUES (:wid, :t, :c, :p)',
            ['wid' => $workflowId, 't' => $data['type'] ?? 'action', 'c' => json_encode($data['config'] ?? []), 'p' => $data['position'] ?? 0],
        );
        $s = $this->connection->fetchOne('SELECT * FROM workflow_steps WHERE id = :id', ['id' => (int) $this->connection->lastInsertId()]);
        $s['config'] = json_decode($s['config'] ?? '{}', true);
        return $s;
    }

    /** @return array[] */
    public function getSteps(int $workflowId): array
    {
        $steps = $this->connection->fetchAll('SELECT * FROM workflow_steps WHERE workflow_id = :wid ORDER BY position', ['wid' => $workflowId]);
        foreach ($steps as &$s) { $s['config'] = json_decode($s['config'] ?? '{}', true); }
        return $steps;
    }

    /**
     * Exécute un workflow manuellement ou sur trigger.
     */
    public function execute(int $workflowId, array $context = []): array
    {
        $workflow = $this->getWorkflow($workflowId);
        if (!$workflow || !$workflow['is_active']) { return ['status' => 'skipped']; }

        $results = [];
        foreach ($workflow['steps'] as $step) {
            $result = $this->executeStep($step, $context);
            $results[] = $result;
            if ($result['status'] === 'failed') { break; }
        }

        return ['status' => 'completed', 'steps' => $results];
    }

    private function executeStep(array $step, array $context): array
    {
        $config = $step['config'];

        return match ($step['type']) {
            'action' => $this->executeAction($config, $context),
            'condition' => $this->evaluateCondition($config, $context),
            'delay' => ['status' => 'delayed', 'seconds' => $config['seconds'] ?? 0],
            default => ['status' => 'unknown_step_type'],
        };
    }

    private function executeAction(array $config, array $context): array
    {
        $action = $config['action'] ?? 'log';

        return match ($action) {
            'send_email' => ['status' => 'executed', 'action' => 'send_email', 'to' => $config['to'] ?? ''],
            'update_record' => ['status' => 'executed', 'action' => 'update_record'],
            'call_webhook' => ['status' => 'executed', 'action' => 'call_webhook', 'url' => $config['url'] ?? ''],
            'create_notification' => ['status' => 'executed', 'action' => 'create_notification'],
            default => ['status' => 'executed', 'action' => $action],
        };
    }

    private function evaluateCondition(array $config, array $context): array
    {
        $field = $config['field'] ?? '';
        $operator = $config['operator'] ?? 'equals';
        $value = $config['value'] ?? '';
        $actual = $context[$field] ?? null;

        $passed = match ($operator) {
            'equals' => $actual == $value,
            'not_equals' => $actual != $value,
            'greater_than' => $actual > $value,
            'less_than' => $actual < $value,
            'contains' => is_string($actual) && str_contains($actual, $value),
            'is_empty' => empty($actual),
            'is_not_empty' => !empty($actual),
            default => false,
        };

        return ['status' => $passed ? 'passed' : 'failed', 'condition' => $config];
    }

    private function ensureTable(): void
    {
        $this->connection->exec('CREATE TABLE IF NOT EXISTS workflows (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255), trigger_type VARCHAR(50) DEFAULT "manual", trigger_config TEXT DEFAULT "{}", is_active BOOLEAN DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
        $this->connection->exec('CREATE TABLE IF NOT EXISTS workflow_steps (id INTEGER PRIMARY KEY AUTOINCREMENT, workflow_id INTEGER, type VARCHAR(30) DEFAULT "action", config TEXT DEFAULT "{}", position INTEGER DEFAULT 0, FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE)');
    }
}
