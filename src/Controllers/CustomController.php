<?php

namespace FlexiAPI\Controllers;

use FlexiAPI\DB\DBAdapterInterface;
use FlexiAPI\Utils\Response;

class CustomController extends BaseController
{
    private array $methods = [];

    public function __construct(DBAdapterInterface $db, array $config)
    {
        parent::__construct($db, $config);

        // ✅ Load user-defined custom methods
        $customFile = __DIR__ . '/../Custom/UserMethods.php';
        if (file_exists($customFile)) {
            $this->methods = include $customFile;
        }
    }

    /**
     * Run a user-defined custom method.
     * Will output JSON and exit via Response::json()
     */
    public function run(string $method, array $params = []): void
    {
        if (!isset($this->methods[$method])) {
            Response::json(false, "Custom method '{$method}' not found", null, 404);
        }

        try {
            $callable = $this->methods[$method];
            $result   = $callable($this->db, $params);

            Response::json(true, "Custom method '{$method}' executed successfully", $result, 200);
        } catch (\Throwable $e) {
            Response::json(false, "Custom method failed: " . $e->getMessage(), null, 500);
        }
    }

    /**
     * List all registered custom methods (for debugging or docs generation).
     */
    public function listMethods(): array
    {
        return array_keys($this->methods);
    }
}
