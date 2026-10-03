<?php

namespace Database\Seeders;

use App\Models\CannedResponse;
use App\Models\Category;
use App\Models\Department;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Support\PriorityMatrix;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Support\ActivityLogStatus;

/** Demo users, a month of realistic ticket history, KB articles and canned replies. */
class DemoSeeder extends Seeder
{
    private const SUBJECTS = [
        'IT Support' => [
            'Laptop won\'t boot after update', 'Cannot connect to VPN from home', 'Outlook keeps asking for password',
            'Printer on 3rd floor is jammed', 'Request: install Adobe Acrobat', 'Monitor flickering intermittently',
            'Locked out of my account', 'Need access to the Finance shared drive', 'Wi-Fi drops in meeting room B',
            'Teams camera not detected', 'New starter needs a laptop', 'Excel crashes when opening large files',
        ],
        'Human Resources' => [
            'Payslip shows incorrect overtime', 'How do I apply for parental leave?', 'Update my bank details',
            'Question about health insurance coverage', 'Onboarding checklist for new hire', 'Leave balance looks wrong',
        ],
        'Facilities' => [
            'Air conditioning not working in Room 204', 'Broken chair at desk 3.14', 'Book large meeting room for town hall',
            'Parking permit renewal', 'Leaking tap in 2nd floor kitchen', 'Light out in stairwell',
        ],
        'Finance' => [
            'Expense claim pending for 3 weeks', 'Purchase request for team licenses', 'Vendor invoice not paid',
            'Corporate card declined', 'Need cost centre code for project',
        ],
    ];

    public function run(): void
    {
        app(ActivityLogStatus::class)->disable();

        $departments = Department::all()->keyBy('name');

        // People
        $admin = User::factory()->admin()->create([
            'name' => 'Admin User', 'email' => 'admin@example.com', 'job_title' => 'Service Desk Manager',
            'department_id' => $departments['IT Support']->id,
        ]);

        $agents = collect([
            ['Alice Santos', 'agent@example.com', 'IT Support', 'IT Support Engineer'],
            ['Ben Cruz', 'ben.cruz@example.com', 'IT Support', 'Systems Administrator'],
            ['Carla Reyes', 'carla.reyes@example.com', 'Human Resources', 'HR Officer'],
            ['Dan Villanueva', 'dan.villanueva@example.com', 'Facilities', 'Facilities Coordinator'],
            ['Eva Mendoza', 'eva.mendoza@example.com', 'Finance', 'Accounts Payable Lead'],
        ])->map(fn ($a) => User::factory()->agent()->create([
            'name' => $a[0], 'email' => $a[1], 'department_id' => $departments[$a[2]]->id, 'job_title' => $a[3],
        ]));

        // Alice is the first-line triager.
        $agents->first()->givePermissionTo('tickets.triage');
        $triagers = [$admin, $agents->first()];

        foreach ($agents->groupBy('department_id') as $deptId => $deptAgents) {
            Department::whereKey($deptId)->update(['lead_id' => $deptAgents->first()->id]);
        }

        $requesters = collect([
            User::factory()->requester()->create(['name' => 'Rachel Requester', 'email' => 'user@example.com', 'job_title' => 'Marketing Specialist']),
        ])->merge(User::factory()->requester()->count(9)->create());

        // Lookups
        $statuses = Status::all()->keyBy('name');
        $priorities = Priority::orderBy('level')->get();
        $tags = Tag::all();

        // Tickets over the past 30 days
        $sequence = 0;
        foreach (range(1, 70) as $i) {
            $deptName = Arr::random(array_keys(self::SUBJECTS));
            $dept = $departments[$deptName];
            $category = Category::where('department_id', $dept->id)->inRandomOrder()->first();
            $priority = $priorities->random();
            $createdAt = Carbon::now()->subMinutes(random_int(30, 30 * 24 * 60));
            $deptAgents = $agents->where('department_id', $dept->id)->values();

            // Older tickets are more likely to be finished.
            $ageDays = $createdAt->diffInDays(now());
            $statusName = Arr::random($ageDays > 7
                ? ['Resolved', 'Closed', 'Closed', 'Resolved', 'In Progress', 'Pending']
                : ['Open', 'Open', 'In Progress', 'In Progress', 'Pending', 'Resolved', 'On Hold']);
            $status = $statuses[$statusName];

            // Recent open tickets may still be waiting for triage; some were never routed.
            $untriaged = $statusName === 'Open' && $ageDays < 3 && random_int(0, 1) === 1;
            $unrouted = $untriaged && random_int(0, 2) === 0;
            $assignee = ($unrouted || ($statusName === 'Open' && random_int(0, 2) === 0)) ? null : $deptAgents->random();

            $ticket = new Ticket([
                'subject' => Arr::random(self::SUBJECTS[$deptName]),
                'description' => fake()->paragraphs(random_int(1, 3), true),
                'requester_id' => $requesters->random()->id,
                'assignee_id' => $assignee?->id,
                'department_id' => $unrouted ? null : $dept->id,
                'category_id' => $unrouted ? null : $category?->id,
                'priority_id' => $priority->id,
                'status_id' => $status->id,
                'source' => Arr::random(['web', 'web', 'web', 'email', 'phone']),
                'impact' => random_int(1, 3),
                'urgency' => random_int(1, 3),
            ]);
            if (! $untriaged) {
                $ticket->triaged_at = $createdAt->copy()->addMinutes(random_int(2, 90))->min(now());
                $ticket->triaged_by = Arr::random($triagers)->id;
            }
            $ticket->reference = 'TKT-'.str_pad((string) ++$sequence, 6, '0', STR_PAD_LEFT);
            $ticket->created_at = $createdAt;
            $ticket->due_response_at = $createdAt->copy()->addMinutes($priority->response_minutes);
            $ticket->due_resolution_at = $createdAt->copy()->addMinutes($priority->resolution_minutes);

            // Response: usually within target, sometimes late, sometimes not yet.
            $respondAfter = null;
            if ($assignee && ($statusName !== 'Open' || random_int(0, 1))) {
                $respondAfter = (int) ($priority->response_minutes * (random_int(0, 4) === 0 ? random_int(110, 300) : random_int(10, 90)) / 100);
                $ticket->first_responded_at = min($createdAt->copy()->addMinutes($respondAfter), now());
            }

            if ($status->is_resolved) {
                $resolveAfter = (int) ($priority->resolution_minutes * (random_int(0, 4) === 0 ? random_int(105, 250) : random_int(15, 95)) / 100);
                $ticket->resolved_at = min($createdAt->copy()->addMinutes(max($resolveAfter, $respondAfter ?? 1)), now());
                $ticket->first_responded_at ??= $ticket->resolved_at;
                $ticket->closed_at = $status->is_closed ? $ticket->resolved_at->copy()->addDay()->min(now()) : null;
            }

            if ($status->pauses_sla) {
                $ticket->sla_paused_at = now()->subHours(random_int(1, 20));
            }

            // Historic breaches are recorded; breaches on open tickets are left for
            // `helpdesk:check-sla` to detect, so they get escalated properly.
            $ticket->response_breached = (bool) $ticket->first_responded_at?->gt($ticket->due_response_at);
            $ticket->resolution_breached = (bool) $ticket->resolved_at?->gt($ticket->due_resolution_at);
            $ticket->updated_at = $ticket->resolved_at ?? $ticket->first_responded_at ?? $createdAt;
            $ticket->save();

            if (random_int(0, 2) === 0) {
                $ticket->tags()->attach($tags->random(random_int(1, 2))->pluck('id'));
            }
            if (random_int(0, 4) === 0) {
                $ticket->watchers()->attach($requesters->where('id', '!=', $ticket->requester_id)->random()->id);
            }

            $this->seedConversation($ticket, $assignee);
        }

        $this->seedTriageQueue($requesters, $departments, $priorities, $statuses['Open'], $sequence);
        $this->seedKnowledgeBase($agents);
        $this->seedCannedResponses($agents);

        app(ActivityLogStatus::class)->enable();
    }

    /** Fresh requester tickets waiting in the triage queue, some without a department. */
    private function seedTriageQueue($requesters, $departments, $priorities, Status $open, int $sequence): void
    {
        foreach ([
            ['Monitor arm came loose, desk 4.22', 'Facilities', 1, 1, 50],
            ['Cannot open the HR portal — blank page', null, 2, 2, 25],
            ['Whole sales team lost access to the CRM', null, 2, 3, 12],
            ['Need a second monitor', 'IT Support', 1, 1, 95],
            ['Reimbursement for conference ticket', 'Finance', 1, 2, 70],
        ] as [$subject, $deptName, $impact, $urgency, $minutesAgo]) {
            $priority = PriorityMatrix::suggest($impact, $urgency, $priorities);
            $createdAt = now()->subMinutes($minutesAgo);

            $ticket = new Ticket([
                'subject' => $subject,
                'description' => fake()->paragraph(),
                'requester_id' => $requesters->random()->id,
                'department_id' => $deptName ? $departments[$deptName]->id : null,
                'priority_id' => $priority->id,
                'status_id' => $open->id,
                'impact' => $impact,
                'urgency' => $urgency,
            ]);
            $ticket->reference = 'TKT-'.str_pad((string) ++$sequence, 6, '0', STR_PAD_LEFT);
            $ticket->created_at = $ticket->updated_at = $createdAt;
            $ticket->due_response_at = $createdAt->copy()->addMinutes($priority->response_minutes);
            $ticket->due_resolution_at = $createdAt->copy()->addMinutes($priority->resolution_minutes);
            $ticket->save();
        }
    }

    private function seedConversation(Ticket $ticket, ?User $assignee): void
    {
        if (! $ticket->first_responded_at || ! $assignee) {
            return;
        }

        $at = $ticket->first_responded_at->copy();
        $end = $ticket->resolved_at ?? now();
        $replies = [
            [$assignee->id, "Hi, thanks for reaching out. I'm looking into this now.", false],
            [$ticket->requester_id, 'Thanks! Let me know if you need anything else from me.', false],
            [$assignee->id, 'Checked the logs — looks related to the recent change. Escalating internally.', true],
            [$assignee->id, "I've applied a fix on our side. Could you please confirm it works for you now?", false],
            [$ticket->requester_id, 'Yes, it works now. Thank you!', false],
        ];

        foreach (array_slice($replies, 0, random_int(1, count($replies))) as [$userId, $body, $internal]) {
            if ($at->gt($end)) {
                break;
            }
            $reply = new TicketReply(['ticket_id' => $ticket->id, 'user_id' => $userId, 'body' => $body, 'is_internal' => $internal]);
            $reply->created_at = $reply->updated_at = $at->copy();
            $reply->saveQuietly();
            $at->addMinutes(random_int(20, 600));
        }
    }

    private function seedKnowledgeBase($agents): void
    {
        $articles = [
            'Getting Started' => [
                ['How to open a helpdesk ticket', 'Raise requests, track progress and get notified.', '<h2>Opening a ticket</h2><p>Click <strong>New ticket</strong> in the sidebar, choose the department and category that best matches your request, and describe the problem in detail.</p><h3>Tips</h3><ul><li>Include error messages and screenshots.</li><li>Add colleagues as watchers to keep them in the loop.</li><li>Reply to the ticket rather than opening a new one.</li></ul>'],
                ['Understanding ticket priorities and response times', 'What Low, Medium, High and Urgent mean.', '<p>Every ticket has a priority that sets its <strong>service level targets</strong>:</p><ul><li><strong>Urgent</strong> — first response in 15 minutes, resolution in 4 hours.</li><li><strong>High</strong> — 1 hour / 8 hours.</li><li><strong>Medium</strong> — 4 hours / 2 days.</li><li><strong>Low</strong> — 8 hours / 5 days.</li></ul><p>The SLA clock pauses while a ticket is <em>Pending</em> on your reply.</p>'],
            ],
            'Accounts & Passwords' => [
                ['Resetting your network password', 'Self-service password reset in three steps.', '<ol><li>Go to the password reset portal.</li><li>Verify your identity with your registered phone.</li><li>Choose a new password of at least 12 characters.</li></ol><p>Your new password syncs to email and VPN within 15 minutes.</p>'],
                ['Setting up multi-factor authentication', 'Protect your account with an authenticator app.', '<p>Install an authenticator app on your phone, then scan the QR code shown at your next sign-in. Keep your recovery codes somewhere safe.</p>'],
            ],
            'Network & VPN' => [
                ['Connecting to the VPN from home', 'Install the client and sign in with your work account.', '<p>Download the VPN client from the software portal, sign in with your work email and approve the MFA prompt. If the connection drops, try switching between Wi-Fi and a wired connection.</p>'],
                ['Troubleshooting slow Wi-Fi in the office', 'Quick checks before you raise a ticket.', '<ul><li>Forget and rejoin the <code>Corp</code> network.</li><li>Move closer to an access point.</li><li>Restart your laptop to renew its IP address.</li></ul>'],
            ],
            'Hardware' => [
                ['Adding a network printer', 'Find and install office printers.', '<p>Open <em>Settings → Printers</em>, choose <strong>Add printer</strong>, and pick the printer named after your floor (e.g. <code>PRN-3F-01</code>).</p>'],
                ['Requesting new equipment', 'Laptops, monitors, keyboards and more.', '<p>Open a ticket in <strong>IT Support → Hardware</strong> with your manager as a watcher. Standard equipment ships within 5 working days.</p>'],
            ],
            'HR Policies' => [
                ['Annual leave policy', 'Entitlement, carry-over and how to book.', '<p>Full-time staff receive 20 days of annual leave per year. Up to 5 unused days may be carried over to the next year. Book leave at least two weeks in advance.</p>'],
                ['Submitting an expense claim', 'Get reimbursed for work expenses.', '<p>Attach itemised receipts and submit within 30 days of purchase. Claims are paid in the next payroll run once approved.</p>'],
            ],
        ];

        foreach ($articles as $categoryName => $items) {
            $category = KbCategory::where('name', $categoryName)->first();
            foreach ($items as [$title, $excerpt, $body]) {
                KbArticle::create([
                    'kb_category_id' => $category->id,
                    'author_id' => $agents->random()->id,
                    'title' => $title,
                    'excerpt' => $excerpt,
                    'body' => $body,
                    'is_published' => true,
                ])->forceFill(['views' => random_int(5, 400), 'helpful_yes' => random_int(0, 40), 'helpful_no' => random_int(0, 6)])->saveQuietly();
            }
        }

        KbArticle::create([
            'kb_category_id' => KbCategory::first()->id,
            'author_id' => $agents->first()->id,
            'title' => 'Draft: new laptop refresh programme',
            'excerpt' => 'Work in progress.',
            'body' => '<p>Details coming soon.</p>',
            'is_published' => false,
        ]);
    }

    private function seedCannedResponses($agents): void
    {
        foreach ([
            ['Acknowledge', "Hi {requester},\n\nThanks for contacting the helpdesk. We've received your request ({reference}) and are looking into it.\n\nBest regards,\n{agent}"],
            ['Request more information', "Hi {requester},\n\nCould you please send us a screenshot of the error and the steps you took before it happened?\n\nThanks,\n{agent}"],
            ['Resolved — please confirm', "Hi {requester},\n\nWe believe this is now resolved. If the issue persists, simply reply to this ticket and it will be reopened.\n\nBest regards,\n{agent}"],
            ['Password reset instructions', "Hi {requester},\n\nYou can reset your password yourself via the self-service portal. See the knowledge base article \"Resetting your network password\" for steps.\n\n{agent}"],
        ] as [$title, $body]) {
            CannedResponse::create(['title' => $title, 'body' => $body]);
        }

        CannedResponse::create([
            'title' => 'VPN quick fix (personal)',
            'body' => "Hi {requester},\n\nPlease disconnect the VPN, restart the client and sign in again. That clears most connection issues.\n\n{agent}",
            'user_id' => $agents->first()->id,
        ]);
    }
}
