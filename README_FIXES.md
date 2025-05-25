# Database and Study Materials Fixes

## Issues Fixed

### 1. Database Error in admin_lab_subjects.php
**Error:** `Unknown column 's.subject_type' in 'where clause'`

**Solution:** The subjects table was missing required columns for lab subjects management.

### 2. Study Materials Upload for Teachers
**Request:** Add functionality for teachers to upload study materials

**Solution:** Created comprehensive study materials management system.

## Steps to Apply Fixes

### Step 1: Update Database Schema
1. Open your browser and navigate to: `http://localhost/student-management/update_subjects_table.php`
2. This will add the missing columns to your subjects table:
   - `subject_type` (ENUM: 'theory', 'lab', 'practical', 'workshop')
   - `lab_room` (VARCHAR: for lab room information)
   - `max_students_per_lab` (INT: maximum students per lab session)

### Step 2: Test Lab Subjects Management
1. Navigate to: `http://localhost/student-management/admin_lab_subjects.php`
2. The error should now be resolved
3. You can add new lab subjects and assign faculty

### Step 3: Access Study Materials Features

#### For Faculty:
1. Navigate to: `http://localhost/student-management/faculty_materials.php`
2. Upload study materials for your subjects
3. Manage visibility (Public, Faculty Only, Private)
4. Delete materials as needed

#### For Students:
1. Navigate to: `http://localhost/student-management/student_materials.php`
2. View and download study materials from enrolled subjects
3. Materials are organized by subject

## New Features Added

### Faculty Study Materials Management
- **Upload Materials:** PDF, DOC, DOCX, PPT, PPTX, TXT, JPG, JPEG, PNG (up to 10MB)
- **Visibility Control:** Public (students can see), Faculty Only, Private
- **Subject-wise Organization:** Materials linked to specific subjects
- **File Management:** View, download, and delete uploaded materials
- **Navigation Integration:** Added to faculty dashboard and subjects page

### Student Study Materials Access
- **Subject-wise View:** Materials organized by enrolled subjects
- **Download Access:** Direct download of public materials
- **Statistics:** Overview of available materials and subjects
- **Responsive Design:** Mobile-friendly interface

### Database Improvements
- **Enhanced Subjects Table:** Added support for lab subjects with room and capacity info
- **File Management:** Improved files table with subject linking and visibility controls
- **Backward Compatibility:** Existing installations will work during transition

## File Structure

### New Files Created:
- `faculty_materials.php` - Faculty study materials management
- `student_materials.php` - Student study materials access
- `update_subjects_table.php` - Database schema update script
- `README_FIXES.md` - This documentation

### Modified Files:
- `admin_lab_subjects.php` - Fixed database queries and added fallback support
- `faculty_dashboard.php` - Added Study Materials navigation and quick action
- `faculty_subjects.php` - Added Study Materials navigation and quick action
- `setup_database.php` - Updated subjects table schema for new installations

## Usage Instructions

### For Faculty:
1. Login to faculty portal
2. Click "Study Materials" in navigation or dashboard
3. Click "Upload Material" button
4. Select subject, add title and description
5. Choose file and set visibility
6. Click "Upload Material"

### For Students:
1. Login to student portal
2. Navigate to "Study Materials"
3. Browse materials by subject
4. Click "Download" to access files

### For Administrators:
1. Run the database update script first
2. Lab subjects management will work without errors
3. Monitor file uploads in the `uploads/study_materials/` directory

## Technical Notes

- Files are stored in `uploads/study_materials/` directory
- File names are automatically generated to prevent conflicts
- Database queries include fallback support for installations without new columns
- All file uploads include security validation for file types and sizes
- Responsive design works on desktop and mobile devices

## Student Access to Study Materials

### Navigation Options for Students:

#### 1. Main Navigation Bar
- **Study Materials** link added to all student pages
- Direct access from any page in the student portal

#### 2. Dashboard Quick Links
- **Study Materials** button in the Quick Links section
- Easy one-click access from the main dashboard

#### 3. Subject Cards
- **Study Materials** button on each subject card
- Quick access to materials for specific subjects

#### 4. Subject Actions
- Materials icon in subject tables
- Integrated with existing subject management

### Student Material Access Features:
- **Subject-wise Organization:** Materials grouped by enrolled subjects
- **Download Access:** Direct download of faculty-uploaded materials
- **Search & Filter:** Easy navigation through materials
- **Mobile Responsive:** Works on all devices
- **Real-time Updates:** See new materials as soon as faculty upload them

### Student Navigation Paths:
1. `student_dashboard.php` → Study Materials link/button → `student_materials.php`
2. `student_subjects.php` → Study Materials button → `student_materials.php`
3. Any subject card → Study Materials button → `student_materials.php`
4. Individual subject → `subject_materials.php?subject_id=X` (specific subject materials)

## Updated Student Pages

### Modified Files for Student Access:
- `student_dashboard.php` - Added Study Materials navigation and quick action button
- `student_subjects.php` - Added Study Materials navigation and subject card buttons
- `student_materials.php` - Main study materials page (already created)
- `subject_materials.php` - Individual subject materials page (newly created)

## Troubleshooting

If you encounter issues:
1. Ensure the `uploads/study_materials/` directory exists and is writable
2. Check that the database update script ran successfully
3. Verify that faculty are assigned to subjects for material uploads
4. Ensure students are enrolled in subjects to see materials
5. Check that students have active enrollments in subjects
6. Verify that faculty have set material visibility to "students" or "public"
