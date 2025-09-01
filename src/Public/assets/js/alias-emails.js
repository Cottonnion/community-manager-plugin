jQuery(function($){
    'use strict';

    if (typeof aliase_emails_data === 'undefined') return;

    const $input = $('#alias_emails');
    const $saveBtn = $('#alias-emails-save');
    const $tagList = $('#alias-email-tags');

    if (!$input.length || !$saveBtn.length || !$tagList.length) return;

    function validateEmail(email){ 
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email); 
    }

    function createTagElement(email, isVerified=false){
        const verifiedClass = isVerified ? 'verified' : 'unverified';
        const badgeText = isVerified ? 'Verified' : 'Unverified';
        return $(`
            <li class="alias-tag ${verifiedClass}" data-email="${email}">
                ${email}
                <span class="remove-tag">×</span>
                <span class="${verifiedClass}-badge">${badgeText}</span>
            </li>
        `);
    }

    function bindRemoveHandlers(){
        $tagList.find('.remove-tag').off('click').on('click', function(e){
            e.preventDefault();
            const $tag = $(this).closest('.alias-tag');
            const email = $tag.data('email');
            Swal.fire({
                title: 'Remove this email?',
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
                            $saveBtn.prop('disabled', false).text('Add Email');
                            if(res.success){
                                $tag.fadeOut(300,function(){ $(this).remove(); });
                                Swal.fire('Removed!','Email removed successfully','success');
                            } else Swal.fire('Error', res.data || 'Failed to remove email','error');
                        }).fail(()=>{
                            $saveBtn.prop('disabled', false).text('Add Email');
                            Swal.fire('Error','AJAX request failed','error');
                        });
                }
            });
        });
    }

    function addEmail(email){
        if(!validateEmail(email)){
            Swal.fire('Invalid','Enter a valid email address','error');
            return;
        }

        if($tagList.find(`[data-email="${email}"]`).length){
            Swal.fire('Duplicate','This email is already added','warning');
            return;
        }

        $saveBtn.prop('disabled', true).text('Adding...');
        $.post(aliase_emails_data.ajax_url, { action: 'add_email_alias', email, nonce: aliase_emails_data.nonce })
            .done(res => {
                $saveBtn.prop('disabled', false).text('Add Email');
                if(res.success){
                    const $newTag = createTagElement(email,false);
                    $tagList.append($newTag.hide().fadeIn(300));
                    $input.val('');
                    bindRemoveHandlers();
                    Swal.fire('Success', res.data || 'Verification email sent','success');
                } else Swal.fire('Error', res.data || 'Failed to add email','error');
            }).fail(()=>{
                $saveBtn.prop('disabled', false).text('Add Email');
                Swal.fire('Error','AJAX request failed','error');
            });
    }

    $saveBtn.on('click', function(){
        const emails = $input.val().split(',').map(e=>e.trim()).filter(Boolean);
        if(!emails.length){
            Swal.fire('Nothing to save','Add at least one email','info');
            return;
        }

        emails.forEach(email => addEmail(email));
    });

    bindRemoveHandlers();
});
