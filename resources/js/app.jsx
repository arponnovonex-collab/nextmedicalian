import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';

// Pages
import Login from './pages/auth/Login';

// Admin Pages
import AdminLayout from './pages/admin/AdminLayout';
import AdminDashboard from './pages/admin/AdminDashboard';
import ExamsManagement from './pages/admin/ExamsManagement';
import CentersManagement from './pages/admin/CentersManagement';
import StudentsManagement from './pages/admin/StudentsManagement';
import StudentImportWorkflow from './pages/admin/StudentImportWorkflow';
import TeachersManagement from './pages/admin/TeachersManagement';
import EvaluationMonitor from './pages/admin/EvaluationMonitor';
import ResultPublishing from './pages/admin/ResultPublishing';
import Reports from './pages/admin/Reports';
import AuditLogs from './pages/admin/AuditLogs';
import SettingsPage from './pages/admin/Settings';

// Teacher Pages
import TeacherLayout from './pages/teacher/TeacherLayout';
import TeacherDashboard from './pages/teacher/TeacherDashboard';
import TeacherHistory from './pages/teacher/TeacherHistory';

// Student Pages
import StudentLayout from './pages/student/StudentLayout';
import StudentDashboard from './pages/student/StudentDashboard';

// Public Verification Pages
import VerifyAdmitCard from './pages/public/VerifyAdmitCard';
import VerifyResult from './pages/public/VerifyResult';

function RootRedirect() {
  const { user, loading } = useAuth();
  if (loading) return null;
  if (!user) return <Navigate to="/login" replace />;
  if (user.role === 'admin') return <Navigate to="/admin" replace />;
  if (user.role === 'teacher') return <Navigate to="/teacher" replace />;
  if (user.role === 'student') return <Navigate to="/student" replace />;
  return <Navigate to="/login" replace />;
}

export function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          {/* Public Authentication & Verification Routes */}
          <Route path="/login" element={<Login />} />
          <Route path="/verify/admit/:token" element={<VerifyAdmitCard />} />
          <Route path="/verify/result/:token" element={<VerifyResult />} />

          {/* Root Redirect */}
          <Route path="/" element={<RootRedirect />} />

          {/* Admin Deck */}
          <Route element={<ProtectedRoute allowedRoles={['admin']} />}>
            <Route path="/admin" element={<AdminLayout />}>
              <Route index element={<AdminDashboard />} />
              <Route path="exams" element={<ExamsManagement />} />
              <Route path="centers" element={<CentersManagement />} />
              <Route path="students" element={<StudentsManagement />} />
              <Route path="import" element={<StudentImportWorkflow />} />
              <Route path="teachers" element={<TeachersManagement />} />
              <Route path="evaluations" element={<EvaluationMonitor />} />
              <Route path="results" element={<ResultPublishing />} />
              <Route path="reports" element={<Reports />} />
              <Route path="audit" element={<AuditLogs />} />
              <Route path="settings" element={<SettingsPage />} />
            </Route>
          </Route>

          {/* Faculty / Teacher Deck */}
          <Route element={<ProtectedRoute allowedRoles={['teacher', 'admin']} />}>
            <Route path="/teacher" element={<TeacherLayout />}>
              <Route index element={<TeacherDashboard />} />
              <Route path="history" element={<TeacherHistory />} />
            </Route>
          </Route>

          {/* Candidate / Student Deck */}
          <Route element={<ProtectedRoute allowedRoles={['student', 'admin']} />}>
            <Route path="/student" element={<StudentLayout />}>
              <Route index element={<StudentDashboard />} />
            </Route>
          </Route>

          {/* Catch-all */}
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}

const rootElement = document.getElementById('root');
if (rootElement) {
  createRoot(rootElement).render(<App />);
}
