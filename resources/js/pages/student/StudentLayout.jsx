import React from 'react';
import { Outlet } from 'react-router-dom';
import Navbar from '../../components/Navbar';

export default function StudentLayout() {
  return (
    <div className="min-h-screen bg-slate-50 flex flex-col">
      <Navbar />
      <main className="flex-1 max-w-4xl w-full mx-auto p-4 sm:p-6 lg:p-8">
        <Outlet />
      </main>
      <footer className="py-6 border-t border-slate-200 text-center text-xs text-slate-400">
        NEXT MEDICALIAN &bull; Central Offline Exam Management System &bull; Powered by EdGenie
      </footer>
    </div>
  );
}
