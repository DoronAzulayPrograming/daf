<?php 
/**
 * @daf-summary Renders all flash messages from the flash store as alert paragraphs and outputs nothing when there are no messages.
 *
 * @daf-note Message types are mapped as: `ok -> success`, `warning -> warning`, `error -> danger`.
 */

/** @var DafCore\IComponent $this  */

/** @var DafCore\Flash\IFlashMessages $flashMessagesStore */
$flashMessagesStore = $this->Inject(DafCore\Flash\IFlashMessages::class);
$flashMessages = $flashMessagesStore->All();

if(count($flashMessages) > 0){?>
    <div <?=$this->RenderAttributes()?>>
        <?php foreach ($flashMessages as $flush_msg) { 
        $class = "alert alert-".match($flush_msg['type']){
            'ok' => "success",
            'warning' => "warning",
            'error' => "danger",
            default => ''
        };
        ?>
            <p class="<?=$class?>"><?= $flush_msg['text'] ?></p>
        <?php } ?>
    </div>
<?php } ?>