<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class WPAE_Executor {
const HOOK = 'wpae_executor_run';

protected $storage;
protected $logger;
protected $registry;

public function __construct( $storage, $logger, $registry ) {
$this->storage  = $storage;
$this->logger   = $logger;
$this->registry = $registry;
}

public function run() {
$plans = $this->storage->get_plans();

if ( empty( $plans ) ) {
$this->logger->log( 'Нет планов для выполнения' );
return;
}

$remaining = array();

foreach ( $plans as $plan ) {
if ( ! $this->execute_plan( $plan ) ) {
$remaining[] = $plan;
}
}

$this->storage->set_plans( $remaining );
$this->logger->log(
'Обработка планов завершена',
array(
'processed' => count( $plans ),
'remaining' => count( $remaining ),
)
);
}

protected function execute_plan( $plan ) {
$action_name = isset( $plan['action'] ) ? sanitize_key( $plan['action'] ) : '';
$action      = $this->registry->get( $action_name );

if ( ! $action ) {
$this->logger->log(
'Обработчик действия не найден',
array(
'action'  => $action_name,
'plan_id' => isset( $plan['id'] ) ? $plan['id'] : '',
)
);
return false;
}

try {
$action->execute( $plan );
$this->logger->log(
'План выполнен',
array(
'plan_id' => isset( $plan['id'] ) ? $plan['id'] : '',
'trigger' => isset( $plan['trigger'] ) ? $plan['trigger'] : '',
)
);
return true;
} catch ( Throwable $throwable ) {
$this->logger->log(
'Ошибка выполнения плана',
array(
'plan_id' => isset( $plan['id'] ) ? $plan['id'] : '',
'error'   => $throwable->getMessage(),
)
);
return false;
}
}
}
