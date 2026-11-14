<?php

return [
    'nav' => [
        'dashboard' => 'Dashboard',
        'take_exam' => 'Take an exam',
        'attempts' => 'My attempts',
        'subscription' => 'Subscription & payments',
        'profile' => 'Profile & security',
        'logout' => 'Log out',
        'menu' => 'Open menu',
        'close' => 'Close menu',
        'main' => 'Main navigation',
    ],

    'dashboard' => [
        'title' => 'Dashboard',
        'greeting' => [
            'morning' => 'Good morning, :name',
            'afternoon' => 'Good afternoon, :name',
            'evening' => 'Good evening, :name',
        ],
        'subtitle' => 'Everything for your ICF exam preparation in one place.',
        'start_exam' => 'Start a new exam',

        'verify' => [
            'text' => 'Please verify your email address to secure your account and receive payment receipts.',
            'action' => 'Verify now',
        ],

        'next' => [
            'label' => 'Your next step',
            'subscribe' => [
                'title' => 'Subscribe to unlock your mock exams',
                'text' => 'Choose a plan and sit as many full mock exams as you like while it is active.',
                'cta' => 'See plans',
            ],
            'resume' => [
                'title' => 'You have an exam in progress',
                'text' => 'Part :part, :answered of :total questions answered. Pick up where you left off.',
                'cta' => 'Resume exam',
            ],
            'first' => [
                'title' => 'Sit your first mock exam',
                'text' => 'Your first attempt shows you the format and sets your starting point. There is no penalty for a low score here.',
                'cta' => 'Start an exam',
            ],
            'improve' => [
                'title' => 'Work on :area, then retake',
                'text' => 'Your last result was lowest in :area at :score%. Review those answers, then sit another exam.',
                'cta' => 'Start another exam',
            ],
        ],

        'sub' => [
            'title' => 'Subscription',
            'active' => 'Active',
            'expired' => 'Expired',
            'none' => 'No active plan',
            'plan' => ':plan plan',
            'days_left' => '{1} :count day left|[2,*] :count days left',
            'valid_until' => 'Valid until :date',
            'expired_on' => 'Ended on :date',
            'none_text' => 'Subscribe to start taking mock exams.',
            'expiring' => 'Your plan ends soon. Renew to keep practising.',
            'renew' => 'Renew plan',
            'subscribe' => 'Subscribe',
            'manage' => 'Manage subscription',
        ],

        'stats' => [
            'attempts' => 'Exams completed',
            'best' => 'Best score',
            'average' => 'Average score',
            'latest' => 'Latest score',
            'none' => '-',
        ],

        'exam' => [
            'title' => 'Start a new exam',
            'lead' => 'Choose the exam you are preparing for. Each attempt draws a fresh random set of questions.',
            'facts' => [
                'parts' => '2 parts',
                'questions' => '40 questions each',
                'minutes' => '70 minutes each',
                'break' => '10 minute break',
            ],
            'rules' => [
                'Find a quiet place and about two and a half hours.',
                'Every question must be answered before you can submit.',
                'When time is up, only answered questions count.',
            ],
            'start' => 'Start :type exam',
            'starting' => 'Preparing your exam...',
            'locked' => 'An active subscription is needed to start an exam.',
            'unavailable' => 'This exam does not have enough questions yet. Please try again later.',
            'locked_cta' => 'Choose a plan',
            'resume_title' => 'Exam in progress',
            'resume_text' => ':type, part :part. :answered of :total answered.',
            'break_left' => 'Break time left',
            'time_left' => 'Time left in this part',
            'resume' => 'Resume exam',
        ],

        'trend' => [
            'title' => 'Score trend',
            'lead' => 'Your last completed exams',
            'empty' => 'Complete an exam to see your progress here.',
        ],

        'focus' => [
            'title' => 'Where to focus',
            'lead' => 'From your latest completed exam',
            'by_profile' => 'By profile type',
            'by_area' => 'By question area',
            'weakest' => 'Lowest area',
            'empty' => 'Your strengths and weak areas appear here after your first completed exam.',
        ],

        'attempts' => [
            'title' => 'Previous attempts',
            'view_all' => 'View all',
            'date' => 'Date',
            'type' => 'Exam',
            'score' => 'Score',
            'status' => 'Status',
            'duration' => 'Time taken',
            'minutes' => ':n min',
            'report' => 'View report',
            'resume' => 'Resume',
            'status_names' => [
                'completed' => 'Completed',
                'in_progress' => 'In progress',
                'expired' => 'Expired',
            ],
            'empty' => 'No exams yet.',
            'empty_cta' => 'Start your first exam',
        ],

        'payments' => [
            'title' => 'Payment history',
            'view_all' => 'View all',
            'date' => 'Date',
            'plan' => 'Plan',
            'amount' => 'Amount',
            'status' => 'Status',
            'receipt' => 'Receipt',
            'retry' => 'Try again',
            'status_names' => [
                'paid' => 'Paid',
                'pending' => 'Pending',
                'failed' => 'Failed',
                'refunded' => 'Refunded',
            ],
            'empty' => 'No payments yet.',
        ],

        'plans' => [
            'title' => 'Choose your plan',
            'lead' => 'Every plan includes unlimited exams, expert feedback and your full performance report.',
            'popular' => 'Most popular',
            'choose' => 'Choose plan',
            'days' => '{1} :count day|[2,*] :count days',
            'features' => [
                'Unlimited mock exams',
                'Feedback on every answer option',
                'Results by competency, skill and value',
            ],
            'secure' => 'Secure online payment.',
        ],

        'help' => [
            'title' => 'Need help?',
            'text' => 'Questions about your account, a payment or a question in the exam? We are happy to help.',
            'cta' => 'Contact support',
        ],
    ],
];
