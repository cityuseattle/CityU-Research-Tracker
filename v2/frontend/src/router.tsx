import { createBrowserRouter, Navigate } from 'react-router-dom'
import ProtectedRoute from './components/guards/ProtectedRoute'
import AppShell from './components/layout/AppShell'
import LoginPage from './pages/LoginPage'
import DashboardPage from './pages/DashboardPage'
import PublicPage from './pages/PublicPage'
import SubmissionsPage from './pages/SubmissionsPage'
import ReviewsPage from './pages/ReviewsPage'
import ReviewerAnalyticsPage from './pages/ReviewerAnalyticsPage'
import WorkflowsPage from './pages/WorkflowsPage'
import UsersPage from './pages/UsersPage'
import ReportsPage from './pages/ReportsPage'
import NotificationsPage from './pages/NotificationsPage'
import SettingsPage from './pages/SettingsPage'
import NewSubmissionPage from './pages/NewSubmissionPage'
import SubmissionDetailPage from './pages/SubmissionDetailPage'
import SubmissionCategoriesPage from './pages/SubmissionCategoriesPage'
import ReviewerAssignmentsPage from './pages/ReviewerAssignmentsPage'
import ResearcherAccessPage from './pages/ResearcherAccessPage'
import AuditLogPage from './pages/AuditLogPage'
import WebhooksPage from './pages/WebhooksPage'
import GatedReviewsPage from './pages/GatedReviewsPage'
import AppealsPage from './pages/AppealsPage'
import CalendarPage from './pages/CalendarPage'
import ProgramsPage from './pages/ProgramsPage'
import ReviewManagementPage from './pages/ReviewManagementPage'
import AnnouncementsPage from './pages/AnnouncementsPage'
import CustomRolesPage from './pages/CustomRolesPage'

export const router = createBrowserRouter(
  [
    // Public landing page — default view (About/Submissions/Login tabs)
    { path: '/', element: <PublicPage /> },

    // Standalone login URL (deep-linkable)
    { path: '/login', element: <LoginPage /> },

    // Authenticated routes — pathless wrapper applies ProtectedRoute + AppShell
    {
      element: (
        <ProtectedRoute>
          <AppShell />
        </ProtectedRoute>
      ),
      children: [
        { path: '/dashboard',              element: <DashboardPage /> },
        { path: '/submissions',            element: <SubmissionsPage /> },
        { path: '/submissions/new',        element: <NewSubmissionPage /> },
        { path: '/submissions/:id',        element: <SubmissionDetailPage /> },
        { path: '/reviews',                element: <ReviewsPage /> },
        { path: '/reviewer-analytics',     element: <ReviewerAnalyticsPage /> },
        { path: '/submission-categories',  element: <SubmissionCategoriesPage /> },
        { path: '/reviewer-assignments',    element: <ReviewerAssignmentsPage /> },
        { path: '/researcher-access',       element: <ResearcherAccessPage /> },
        { path: '/workflows',              element: <WorkflowsPage /> },
        { path: '/users',                  element: <UsersPage /> },
        { path: '/reports',                element: <ReportsPage /> },
        { path: '/notifications',          element: <NotificationsPage /> },
        { path: '/settings',               element: <SettingsPage /> },
        { path: '/audit-log',              element: <AuditLogPage /> },
        { path: '/webhooks',               element: <WebhooksPage /> },
        { path: '/gated-reviews',          element: <GatedReviewsPage /> },
        { path: '/appeals',                element: <AppealsPage /> },
        { path: '/calendar',               element: <CalendarPage /> },
        { path: '/programs',               element: <ProgramsPage /> },
        { path: '/review-management',       element: <ReviewManagementPage /> },
        { path: '/announcements',          element: <AnnouncementsPage /> },
        { path: '/custom-roles',           element: <CustomRolesPage /> },
      ],
    },

    // Catch-all → home
    { path: '*', element: <Navigate to="/" replace /> },
  ],
  { basename: '/app' },
)

