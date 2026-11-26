import React from 'react';
import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import ChangePasswordModal from '../pages/auth/ChangePasswordModal';

export default function ProtectedRoute({ allowedRoles = [] }) {
  const { user, loading, role, mustChangePassword } = useAuth();

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-slate-50">
        <div className="flex flex-col items-center gap-3">
          <div className="w-10 h-10 border-4 border-slate-200 border-t-blue-600 rounded-full animate-spin"></div>
          <p className="text-sm font-medium text-slate-500">Loading NEXT MEDICALIAN...</p>
        </div>
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (allowedRoles.length > 0 && !allowedRoles.includes(role)) {
    // Redirect to respective dashboard if unauthorized
    if (role === 'admin') return <Navigate to="/admin" replace />;
    if (role === 'teacher') return <Navigate to="/teacher" replace />;
    if (role === 'student') return <Navigate to="/student" replace />;
    return <Navigate to="/login" replace />;
  }

  return (
    <>
      {mustChangePassword && role !== 'student' && <ChangePasswordModal />}
      <Outlet />
    </>
  );
}
