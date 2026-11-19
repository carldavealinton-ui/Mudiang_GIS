# New Features Added to Barangay Mudiang GIS System

## 1. Notification System

### Overview
Residents now receive real-time notifications when their complaint status is updated by admin/staff.

### Files Created/Modified:
- **database.sql** - Added `notifications` table
- **get_notifications.php** - API endpoint to fetch notifications
- **mark_notifications_read.php** - Marks all notifications as read
- **admin/edit_complaint.php** - Inserts notification on status change
- **resident_dashboard.php** - Added notification bell icon with dropdown

### Features:
- 🔔 Bell icon in topbar shows unread count badge
- Dropdown displays last 20 notifications
- Color-coded icons based on status (Pending/In Progress/Resolved)
- "Mark all as read" functionality
- Auto-refresh every 30 seconds
- Time ago format (e.g., "5m ago", "2h ago")

### Database Schema:
```sql
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    complaint_id INT NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
);
```

### How It Works:
1. Admin updates complaint status in edit_complaint.php
2. System detects status change and creates notification
3. Notification appears in resident's dashboard bell icon
4. Resident clicks bell to view notifications
5. Clicking "Mark all as read" clears the badge

---

## 2. AI Chatbot Assistant

### Overview
Interactive AI chatbot that helps users with common questions about barangay services, complaint submission, registration, and more.

### Files Created:
- **chatbot.php** - Backend API with keyword-based AI responses
- **chatbot_widget.php** - Reusable chatbot UI component

### Files Modified:
- **index.php** - Added chatbot widget
- **login.php** - Added chatbot widget
- **register.php** - Added chatbot widget
- **resident_dashboard.php** - Added chatbot widget

### Features:
- 🤖 Floating chat button (bottom-right corner)
- Real-time conversation interface
- Typing indicator animation
- Context-aware responses
- Mobile responsive design
- Available on all public pages

### Chatbot Capabilities:
The AI assistant can answer questions about:

1. **Complaint Submission**
   - How to submit complaints
   - Complaint types available
   - Guest vs registered submission

2. **Status Tracking**
   - How to check complaint status
   - Understanding status colors
   - Notification system

3. **Registration**
   - Step-by-step registration guide
   - Gmail verification process
   - Account activation

4. **Login Issues**
   - Troubleshooting login problems
   - Password recovery
   - Email verification

5. **Services**
   - Available barangay services
   - System features
   - Priority levels

6. **Contact Information**
   - Barangay office details
   - Office hours
   - Contact methods

### Sample Interactions:

**User:** "How do I submit a complaint?"
**Bot:** Provides step-by-step guide with location pinning, form filling, and submission process.

**User:** "What are the complaint types?"
**Bot:** Lists all available complaint categories (Road Damage, Garbage, Streetlight, etc.)

**User:** "I can't login"
**Bot:** Provides troubleshooting steps for login issues.

### Technical Details:
- **Backend:** PHP with keyword pattern matching
- **Frontend:** Vanilla JavaScript (no dependencies)
- **Design:** Modern chat UI with smooth animations
- **Response Time:** ~800ms simulated typing delay
- **Extensible:** Easy to add new response patterns

---

## Installation Instructions

### 1. Update Database
Run the following SQL in phpMyAdmin or MySQL command line:

```sql
USE barangay_gis;

CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    complaint_id INT NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
);

ALTER TABLE notifications ADD INDEX IF NOT EXISTS idx_notif_user (user_id, is_read);
```

### 2. Test Notifications
1. Login as admin
2. Edit any complaint and change its status
3. Login as the resident who submitted that complaint
4. Check the bell icon in the dashboard - you should see a notification

### 3. Test Chatbot
1. Visit any page (login, register, or dashboard)
2. Click the blue chat button in bottom-right corner
3. Type messages like:
   - "How do I submit a complaint?"
   - "What services do you offer?"
   - "I need help with registration"

---

## Usage Tips

### For Residents:
- Check the notification bell regularly for complaint updates
- Use the chatbot for quick answers instead of calling the office
- Mark notifications as read to keep your inbox clean

### For Administrators:
- Always add resolution notes when updating complaint status
- Notifications are automatically sent when status changes
- Monitor chatbot usage to identify common user questions

### For Developers:
- Add new chatbot responses in `chatbot.php`
- Customize notification polling interval in `resident_dashboard.php` (default: 30 seconds)
- Extend notification types by modifying `admin/edit_complaint.php`

---

## Future Enhancements

### Notifications:
- Email notifications for critical updates
- SMS integration
- Push notifications (PWA)
- Notification preferences/settings

### Chatbot:
- Natural Language Processing (NLP) integration
- Multi-language support
- Voice input/output
- Integration with OpenAI API for smarter responses
- Chat history persistence
- File/image sharing capability

---

## Support

For issues or questions:
- Check the chatbot first for quick answers
- Contact barangay IT administrator
- Review this documentation

---

**Version:** 2.0
**Last Updated:** <?php echo date('F Y'); ?>
**Developed for:** Barangay Mudiang GIS Complaint System
