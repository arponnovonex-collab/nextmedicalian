import React from 'react';
import { NavLink } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import {
  LayoutDashboard,
  CalendarCheck,
  MapPin,
  Users,
  UploadCloud,
  GraduationCap,
  FileCheck,
  Award,
  FileSpreadsheet,
  Activity,
  Settings,
  PenTool,
  History,
  QrCode,
  FileText
} from 'lucide-react';

export default function Sidebar({ isOpen, onClose }) {
  const { role } = useAuth();

  const adminNav = [
    { name: 'Dashboard', path: '/admin', icon: LayoutDashboard, exact: true },
    { name: 'Examinations', path: '/admin/exams', icon: CalendarCheck },
    { name: 'Zones & Centers', path: '/admin/centers', icon: MapPin },
    { name: 'Student Registry', path: '/admin/students', icon: Users },
    { name: 'Excel Import', path: '/admin/import', icon: UploadCloud },
    { name: 'Faculty Management', path: '/admin/teachers', icon: GraduationCap },
    { name: 'Evaluation Monitor', path: '/admin/evaluations', icon: FileCheck },
    { name: 'Result & Merit List', path: '/admin/results', icon: Award },
    { name: 'Reports & Seat Plans', path: '/admin/reports', icon: FileSpreadsheet },
    { name: 'Audit Logs', path: '/admin/audit', icon: Activity },
    { name: 'Settings', path: '/admin/settings', icon: Settings },
  ];

  const teacherNav = [
    { name: 'Evaluation Portal', path: '/teacher', icon: PenTool, exact: true },
    { name: 'Submission History', path: '/teacher/history', icon: History },
  ];

  const studentNav = [
    { name: 'My Dashboard', path: '/student', icon: LayoutDashboard, exact: true },
  ];

  const navItems = role === 'admin' ? adminNav : role === 'teacher' ? teacherNav : studentNav;

  return (
    <>
      {/* Mobile Backdrop */}
      {isOpen && (
        <div
          onClick={onClose}
          className="fixed inset-0 bg-slate-900/40 z-40 lg:hidden backdrop-blur-xs"
        />
      )}

      <aside className={`
        fixed top-0 bottom-0 left-0 z-40 w-64 bg-slate-900 text-slate-300 flex flex-col transition-transform duration-200 ease-in-out
        lg:static lg:translate-x-0
        ${isOpen ? 'translate-x-0' : '-translate-x-full'}
      `}>
        {/* Logo Brand */}
        <div className="h-16 flex items-center px-6 gap-3 border-b border-slate-800">
          <div className="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center font-extrabold text-sm tracking-tight shadow-md">
            NM
          </div>
          <div>
            <div className="text-[10px] font-bold text-blue-400 uppercase tracking-widest leading-none">EdGenie</div>
            <div className="text-sm font-extrabold text-white tracking-tight leading-none mt-0.5">NEXT MEDICALIAN</div>
          </div>
        </div>

        {/* Navigation items */}
        <div className="flex-1 py-4 px-3 overflow-y-auto space-y-1">
          <div className="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-500">
            {role === 'admin' ? 'Administration' : role === 'teacher' ? 'Evaluation Deck' : 'Candidate Portal'}
          </div>

          {navItems.map((item) => {
            const Icon = item.icon;
            return (
              <NavLink
                key={item.path}
                to={item.path}
                end={item.exact}
                onClick={() => onClose && onClose()}
                className={({ isActive }) => `
                  flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-semibold transition-all
                  ${isActive
                    ? 'bg-blue-600 text-white shadow-sm'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800/80'}
                `}
              >
                <Icon className="w-4 h-4 shrink-0" />
                <span>{item.name}</span>
              </NavLink>
            );
          })}
        </div>

        {/* System footer */}
        <div className="p-4 border-t border-slate-800 text-[11px] text-slate-500 text-center">
          <div>Central Exam Management</div>
          <div className="text-slate-600 mt-0.5">v2.0 &bull; Asia/Dhaka</div>
        </div>
      </aside>
    </>
  );
}
