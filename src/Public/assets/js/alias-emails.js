jQuery(function($){
    'use strict';

    if (typeof aliase_emails_data === 'undefined') return;

    const $input = $('#alias_emails');
    const $saveBtn = $('#alias-emails-save');
    const $tagList = $('#alias-email-tags');

    if (!$input.length || !$saveBtn.length || !$tagList.length) return;

    const $tooltip = $('<div id="alias-tooltip" class="alias-tooltip"></div>').hide().appendTo('body');
    $('span.woocommerce-help-tip').hover(function(){
        const tip = $(this).data('tip');
        $tooltip.text(tip).fadeIn(200);
        const offset = $(this).offset();
        const tipHeight = $tooltip.outerHeight();
        $tooltip.css({
            top: offset.top - tipHeight - 6,
            left: offset.left - ($tooltip.outerWidth()/2) + $(this).outerWidth()/2,
            position: 'absolute',
            zIndex: 9999
        });
    }, function(){ $tooltip.fadeOut(200); });

    function validateEmail(email){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email); }
    function getExistingEmails(){ return $tagList.find('.alias-tag').map((i, el)=>$(el).data('email')).get(); }
    function updateEmptyState(){ $tagList.toggleClass('no-aliases', !$tagList.find('.alias-tag').length); }

    function bindRemoveHandlers(){
        $tagList.find('.remove-tag').off('click').on('click', function(e){
            e.preventDefault();
            const $tag = $(this).closest('.alias-tag');
            const email = $tag.data('email');
            Swal.fire({
                title: 'Remove this alias?',
                text: email,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, remove'
            }).then((result) => {
                if(result.isConfirmed){
                    $saveBtn.prop('disabled', true).text('Removing...');
                    $.post(aliase_emails_data.ajax_url, { action: 'remove_email_alias', email, nonce: aliase_emails_data.nonce })
                     .done(res => {
                        $saveBtn.prop('disabled', false).text('Save Aliases');
                        if(res.success){
                            $tag.fadeOut(300,function(){ $(this).remove(); updateEmptyState(); });
                            Swal.fire('Removed!', 'Alias removed successfully','success');
                        } else Swal.fire('Error', res.data || 'Failed to remove alias','error');
                     }).fail(()=>{ $saveBtn.prop('disabled', false).text('Save Aliases'); Swal.fire('Error','AJAX request failed','error'); });
                }
            });
        });
    }

    function createTagElement(email,isVerified=false){
        const verifiedClass = isVerified?'verified':'unverified';
        const badgeText = isVerified?'Verified':'Unverified';
        return $(`
            <li class="alias-tag ${verifiedClass}" data-email="${email}">
                ${email}
                <span class="remove-tag">×</span>
                <span class="${isVerified?'verified':'unverified'}-badge">${badgeText}</span>
            </li>
        `);
    }

    function addEmail(email){
        if(!validateEmail(email)){ Swal.fire('Invalid','Enter a valid email address','error'); return; }
        if(getExistingEmails().includes(email)){ Swal.fire('Duplicate','This email is already added','warning'); return; }

        $saveBtn.prop('disabled', true).text('Adding...');
        $.post(aliase_emails_data.ajax_url, { action: 'add_email_alias', email, nonce: aliase_emails_data.nonce })
         .done(res => {
            $saveBtn.prop('disabled', false).text('Save Aliases');
            if(res.success){
                const $newTag = createTagElement(email,false);
                $tagList.append($newTag.hide().fadeIn(300));
                updateEmptyState();
                bindRemoveHandlers();
                $input.val('');
                Swal.fire('Success', res.data || 'Verification email sent','success');
            } else Swal.fire('Error', res.data || 'Failed to add alias','error');
         }).fail(()=>{ $saveBtn.prop('disabled', false).text('Save Aliases'); Swal.fire('Error','AJAX request failed','error'); });
    }

    $input.on('keypress', function(e){ if(e.key==='Enter'||e.key===','){ e.preventDefault(); let email=$input.val().trim(); if(email) addEmail(email); } });

    $saveBtn.on('click', function(){
        const emails = getExistingEmails();
        if(!emails.length){ Swal.fire('Nothing to save','Add at least one alias','info'); return; }

        $saveBtn.prop('disabled', true).text('Saving...');
        $.post(aliase_emails_data.ajax_url, { action:'save_email_aliases', emails, nonce: aliase_emails_data.nonce })
         .done(res => { $saveBtn.prop('disabled', false).text('Save Aliases'); if(res.success) Swal.fire('Saved','Aliases saved successfully','success'); else Swal.fire('Error', res.data || 'Failed to save aliases','error'); })
         .fail(()=>{ $saveBtn.prop('disabled', false).text('Save Aliases'); Swal.fire('Error','AJAX request failed','error'); });
    });

    bindRemoveHandlers();
    updateEmptyState();
});
