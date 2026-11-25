import React, { useState, useEffect } from 'react';
import api from '../../api/axios';
import {
  Users,
  CalendarCheck,
  CheckCircle2,
  Clock,
  MapPin,
  Building2,
  FileCheck2,
  AlertCircle,
  DownloadCloud,
  Award,
  Filter
} from 'lucide-react';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell
} from 'recharts';

export default function AdminDashboard() {
  const [stats, setStats] = useState(null);
  const [exams, setExams] = useState([]);
  const [selectedExamId, setSelectedExamId] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchExamsList();
  }, []);

  useEffect(() => {
    fetchDashboardStats(selectedExamId);
  }, [selectedExamId]);

  const fetchExamsList = async () => {
    try {
      const res = await api.get('/admin/exams?per_page=100');
      if (res.data.success) {
        setExams(res.data.data.data);
      }
    } catch (e) {
      // ignore
    }
  };

  const fetchDashboardStats = async (examId) => {
    setLoading(true);
    try {
      const url = examId ? `/admin/dashboard-stats?exam_id=${examId}` : '/admin/dashboard-stats';
      const res = await api.get(url);
      if (res.data.success) {
        setStats(res.data.data);
      }
    } catch (e) {
      // ignore
    } finally {
      setLoading(false);
    }
  };

  const COLORS = ['#2563EB', '#059669', '#D97706', '#E11D48', '#7C3AED', '#0891B2'];

  if (loading && !stats) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="w-8 h-8 border-4 border-slate-200 border-t-blue-600 rounded-full animate-spin"></div>
      </div>
    );
  }

  const s = stats?.summary || {};
  const charts = stats?.charts || {};

  return (
    <div className="space-y-6">
      {/* Header & Exam Filter */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">System Overview</h1>
          <p className="text-xs text-slate-500 mt-1">Central offline examination administration and live analytics</p>
        </div>

        <div className="flex items-center gap-2 bg-white px-3 py-2 rounded-xl border border-slate-200 shadow-xs">
          <Filter className="w-4 h-4 text-slate-400" />
          <span className="text-xs font-semibold text-slate-600">Scope:</span>
          <select
            value={selectedExamId}
            onChange={(e) => setSelectedExamId(e.target.value)}
            className="text-xs font-bold text-slate-800 bg-transparent focus:outline-hidden cursor-pointer"
          >
            <option value="">Global Statistics (All Exams)</option>
            {exams.map(e => (
              <option key={e.id} value={e.id}>{e.code} - {e.title}</option>
            ))}
          </select>
        </div>
      </div>

      {/* KPI Cards Grid */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <div className="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
          <div className="flex items-center justify-between text-slate-500 mb-2">
            <span className="text-[11px] font-bold uppercase tracking-wider">Registered Students</span>
            <Users className="w-4 h-4 text-blue-600" />
          </div>
          <div className="text-2xl font-black text-slate-900">{s.total_registered_students || 0}</div>
          <div className="text-[10px] text-slate-400 mt-1">Enrolled candidates</div>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
          <div className="flex items-center justify-between text-slate-500 mb-2">
            <span className="text-[11px] font-bold uppercase tracking-wider">Evaluated Scripts</span>
            <FileCheck2 className="w-4 h-4 text-emerald-600" />
          </div>
          <div className="text-2xl font-black text-emerald-600">{s.total_evaluated || 0}</div>
          <div className="text-[10px] text-slate-400 mt-1">Completed evaluations</div>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
          <div className="flex items-center justify-between text-slate-500 mb-2">
            <span className="text-[11px] font-bold uppercase tracking-wider">Pending Scripts</span>
            <Clock className="w-4 h-4 text-amber-500" />
          </div>
          <div className="text-2xl font-black text-amber-600">{s.pending_evaluations || 0}</div>
          <div className="text-[10px] text-slate-400 mt-1">Awaiting marks entry</div>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
          <div className="flex items-center justify-between text-slate-500 mb-2">
            <span className="text-[11px] font-bold uppercase tracking-wider">Admit Downloads</span>
            <DownloadCloud className="w-4 h-4 text-indigo-600" />
          </div>
          <div className="text-2xl font-black text-slate-900">{s.admit_card_downloads || 0}</div>
          <div className="text-[10px] text-slate-400 mt-1">Candidate access</div>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
          <div className="flex items-center justify-between text-slate-500 mb-2">
            <span className="text-[11px] font-bold uppercase tracking-wider">Centers & Rooms</span>
            <Building2 className="w-4 h-4 text-slate-600" />
          </div>
          <div className="text-2xl font-black text-slate-900">{s.total_centers || 0}</div>
          <div className="text-[10px] text-slate-400 mt-1">Across {s.total_zones || 0} zones</div>
        </div>
      </div>

      {/* Visual Analytics Charts */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Zone-wise Student Distribution */}
        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
          <h3 className="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
            <MapPin className="w-4 h-4 text-blue-600" /> Zone-wise Candidate Distribution
          </h3>
          <div className="h-64">
            {charts.zone_distribution && charts.zone_distribution.length > 0 ? (
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={charts.zone_distribution}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
                  <XAxis dataKey="zone" tick={{ fontSize: 11 }} />
                  <YAxis tick={{ fontSize: 11 }} />
                  <Tooltip />
                  <Bar dataKey="students" fill="#2563EB" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-full flex items-center justify-center text-xs text-slate-400">
                No zone allocation data recorded yet
              </div>
            )}
          </div>
        </div>

        {/* Marks Brackets Distribution */}
        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
          <h3 className="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
            <Award className="w-4 h-4 text-amber-500" /> Marks Bracket Distribution
          </h3>
          <div className="h-64">
            {charts.marks_distribution ? (
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={charts.marks_distribution}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
                  <XAxis dataKey="bracket" tick={{ fontSize: 10 }} />
                  <YAxis tick={{ fontSize: 11 }} />
                  <Tooltip />
                  <Bar dataKey="count" fill="#059669" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-full flex items-center justify-center text-xs text-slate-400">
                No marks evaluated yet
              </div>
            )}
          </div>
        </div>

        {/* Teacher Evaluation Performance */}
        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs lg:col-span-2">
          <h3 className="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
            <FileCheck2 className="w-4 h-4 text-purple-600" /> Faculty Script Evaluation Progress
          </h3>
          <div className="h-56">
            {charts.teacher_evaluations && charts.teacher_evaluations.length > 0 ? (
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={charts.teacher_evaluations} layout="vertical">
                  <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
                  <XAxis type="number" tick={{ fontSize: 11 }} />
                  <YAxis type="category" dataKey="teacher" tick={{ fontSize: 11 }} width={140} />
                  <Tooltip />
                  <Bar dataKey="evaluated_count" fill="#7C3AED" radius={[0, 4, 4, 0]} />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-full flex items-center justify-center text-xs text-slate-400">
                No teacher evaluation records submitted yet
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
