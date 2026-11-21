# Growth OS architecture

## Navigation model

### 1. Today
Dynamic operating dashboard for the current day.
Examples:
- key tasks today;
- overdue count;
- next/scheduled workout;
- Quran reading;
- Person of the Day;
- evening journal prompt;
- optional AI analysis entry point.

Today should summarize. Full content opens in a dedicated screen/modal/service.

### 2. Plan
Single future-facing planning surface.
Contains:
- compact next-7-days calendar/agenda;
- Tasks workspace;
- Projects workspace;
- scheduled workouts;
- eventually other scheduled service events.

Do not create separate competing calendars if a module can project its events here.

### 3. Journal
Reflection layer.
Current modules:
- Daily journal
- CEO Review

Potential future additions: notes/reflections, but not operational task management.

### 4. Progress
Cross-module metrics and trends.
Current/target examples:
- task completion rate;
- Quran progress/readings/streak;
- Person of the Day count/streak;
- workout adherence and exercise progression;
- journal completion;
- project completion/progress.

Later AI can create weekly/monthly synthesis from explicit user-selected data.

### 5. Services
The extensibility directory. New services appear here instead of in bottom nav.
Current service set:
- Tasks & Projects
- Journal
- Workouts
- Quran
- Person of the Day
- Knowledge Base
- Capital
- CEO Review
- AI
- Export

Potential future services:
- Sleep
- Nutrition
- Weight/body metrics
- Books/reading
- Networking/contacts
- Habits

## Service integration contract
Each service should own its domain data, but may expose:
- Today summary/event
- Plan/calendar event
- Progress metrics

Example workout flow:
Workout service owns program + exercises + sets + sessions.
→ Plan shows scheduled workout.
→ Today shows today’s workout.
→ Progress shows adherence and performance.

Example project flow:
Project owns outcome and task association.
→ Plan shows actionable tasks.
→ Today shows tasks due today.
→ Progress shows project completion.
