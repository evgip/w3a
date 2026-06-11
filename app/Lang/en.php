<?php
return [
    'buttons' => [
        'submit' => 'Отправить',
        'cancel' => 'Отмена',
    ],
    'errors' => [
        '404' => 'Упс! Страница не найдена.',
    ],

    // EMAIL TEMPLATE ELEMENTS
    'email_activation_subject' => '🚀 Activate your account on MyShare',
    'email_activation_body'    => '<h3>Welcome to the community, %s!</h3>
                                    <p>You have successfully registered on the MyShare platform.</p>
                                    <p>To activate your account and gain the ability to submit posts and leave comments, please click the following link:</p>
                                    <p><a href="%s"><strong>Confirm registration and activate account</strong></a></p>
                                    <br>
                                    <p><em>If you did not register on our website, simply ignore this email.</em></p>',

    'email_recovery_subject'   => '🔒 Password Recovery on MyShare',
    'email_recovery_body'      => '<h3>Access Recovery Request</h3>
                                    <p>Hello, %s!</p>
                                    <p>We received a request to reset the password for your account.</p>
                                    <p>To create a new secure password, please follow this secure link:</p>
                                    <p><a href="%s"><strong>Create a new password and log in</strong></a></p>
                                    <br>
                                    <p><strong>Warning:</strong> This link is valid for 1 hour. If you did not request a password reset, delete this email immediately.</p>'
];
