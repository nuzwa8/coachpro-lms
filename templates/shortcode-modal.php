<?php
// 🇵🇰 PHP Phase Start: Modal Template 🇵🇰
?>
<div id="ssm-gpt-modal" class="ssm-modal">
    <div class="ssm-modal-content">
        <span class="ssm-close-button">&times;</span>
        <div class="ssm-modal-header">
            <h3 id="ssm-modal-title"></h3>
            <p id="ssm-modal-description"></p>
        </div>
        
        <div class="ssm-modal-body">
            <div class="ssm-form-area">
                <h4><?php esc_html_e( 'Configure Your Prompt', 'ssm-gpt-launcher' ); ?></h4>
                
                <form id="ssm-prompt-builder-form" class="ssm-prompt-builder-form">
                    <div id="ssm-dynamic-fields">
                        </div>
                    
                    <button type="submit" id="ssm-launch-button" class="ssm-launch-button">
                        <span class="default-text"><?php esc_html_e( 'Copy Prompt & Launch GPT', 'ssm-gpt-launcher' ); ?></span>
                        <span class="copied-text" style="display: none;"><?php esc_html_e( 'Copied! Launching...', 'ssm-gpt-launcher' ); ?></span>
                    </button>
                    
                    <p class="ssm-feedback-message" style="display: none;"><?php esc_html_e( 'The generated prompt has been copied to your clipboard. Please paste it into the GPT chat window.', 'ssm-gpt-launcher' ); ?></p>
                </form>
            </div>
        </div>
    </div>
</div>
