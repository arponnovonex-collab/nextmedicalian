import React, { useState, useEffect } from 'react';
import api, { downloadFile } from '../../api/axios';
import {
  Award,
  AlertTriangle,
  CheckCircle2,
  Clock,
  Download,
  Search,
  Filter,
  Sparkles,
  ArrowRight,
  ShieldAlert,
  BarChart2
} from 'lucide-react';
import confetti from 'canvas-confetti';

export default function ResultPublishing() {
  const [exams, setExams] = useState([]);
  const [selectedExamId, setSelectedExamId] = useState('');
  const [previewData, setPreviewData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [publishing, setPublishing] = useState(false);
  const [search, setSearch] = useState('');

  useEffect(() => {
    fetchExams();
  }, []);

  useEffect(() => {
    if (selectedExamId) {
      fetchResultPreview();
    }
  }, [selectedExamId]);

  const fetchExams = async () => {
    try {
      const res = await api.get('/admin/exams?per_page=100');
      if (res.data.success) {
        setExams(res.data.data.data);
        if (res.data.data.data.length > 0) {
          setSelectedExamId(res.data.data.data[0].id);
        }
      }
    } catch (e) {}
  };

  const fetchResultPreview = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/admin/results/preview?exam_id=${selectedExamId}`);
      if (res.data.success) {
        setPreviewData(res.data.data);
      }
    } catch (e) {
      // ignore
    } finally {
      setLoading(false);
    }
  };

  const handlePublish = async (confirmIncomplete = false) => {
    if (!confirm('Are you sure you want to officially publish examination results? Once published, candidate results and merit ranks will become visible on the student portal and verification links.')) {
      return;
    }

    setPublishing(true);
    try {
      const res = await api.post('/admin/results/publish', {
        exam_id: selectedExamId,
        confirm_incomplete: confirmIncomplete,
      });

      if (res.data.success) {
        confetti({
          particleCount: 100,
          spread: 70,
          origin: { y: 0.6 }
        });
        alert(res.data.message);
        fetchResultPreview();
        fetchExams();
      }
    } catch (err) {
      if (err.response?.data?.requires_confirmation) {
        if (confirm(`${err.response.data.message}\n\nDo you want to proceed and publish anyway?`)) {
          handlePublish(true);
          return;
        }
      } else {
        alert(err.response?.data?.message || 'Error publishing results.');
      }
    } finally {
      setPublishing(false);
    }
  };

  const handleExportMeritList = () => {
    downloadFile(`/admin/results/export?exam_id=${selectedExamId}`, `merit_list_exam_${selectedExamId}.csv`);
  };

  const s = previewData?.summary || {};
  const warnings = previewData?.warnings || [];
  const meritList = previewData?.merit_list_preview || [];

  const filteredMerit = meritList.filter(item => {
    if (!search) return true;
    const q = search.toLowerCase();
    return item.student_name.toLowerCase().includes(q)
      || item.registration_number.toLowerCase().includes(q)
      || (item.institution && item.institution.toLowerCase().includes(q));
  });

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Result Processing & Merit List</h1>
          <p className="text-xs text-slate-500 mt-1">Multi-tier tie-breaking calculation, merit rankings, pre-publication diagnostics, and official release.</p>
        </div>

        <div className="flex items-center gap-3">
          <button
            onClick={handleExportMeritList}
            disabled={!selectedExamId}
            className="inline-flex items-center gap-2 px-3.5 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold uppercase tracking-wider shadow-xs transition-colors cursor-pointer"
          >
            <Download className="w-4 h-4 text-blue-600" /> Export Merit List (.CSV)
          </button>

          <button
            onClick={() => handlePublish(false)}
            disabled={publishing || !selectedExamId}
            className="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm transition-colors cursor-pointer disabled:opacity-50"
          >
            <Sparkles className="w-4 h-4" /> {publishing ? 'Publishing...' : 'Publish Official Results'}
          </button>
        </div>
      </div>

      {/* Scope Selector */}
      <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
        <div className="flex items-center gap-3">
          <span className="text-xs font-bold text-slate-700 uppercase tracking-wider">Select Examination:</span>
          <select
            value={selectedExamId}
            onChange={(e) => setSelectedExamId(e.target.value)}
            className="text-xs font-semibold px-3 py-1.5 border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-blue-600"
          >
            {exams.map(e => (
              <option key={e.id} value={e.id}>
                {e.code} - {e.title} ({e.status === 'result_published' ? 'PUBLISHED' : e.status})
              </option>
            ))}
          </select>
        </div>

        {previewData?.exam?.status === 'result_published' ? (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-xs font-bold uppercase tracking-wider">
            <CheckCircle2 className="w-4 h-4" /> Results Live & Published
          </span>
        ) : (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full text-xs font-bold uppercase tracking-wider">
            <Clock className="w-4 h-4" /> Pre-Publication Draft
          </span>
        )}
      </div>

      {/* Diagnostics Warnings */}
      {warnings.length > 0 && (
        <div className="bg-amber-50 border border-amber-200 p-4 rounded-2xl space-y-1.5">
          <h4 className="text-xs font-bold text-amber-900 uppercase tracking-wider flex items-center gap-1.5">
            <AlertTriangle className="w-4 h-4 text-amber-600" /> Pre-Publication Diagnostic Warnings
          </h4>
          <ul className="text-xs text-amber-800 list-disc list-inside space-y-0.5">
            {warnings.map((w, idx) => (
              <li key={idx}>{w}</li>
            ))}
          </ul>
        </div>
      )}

      {/* Analytics Summary */}
      <div className="grid grid-cols-2 sm:grid-cols-6 gap-3">
        <div className="bg-white p-3.5 rounded-xl border border-slate-200">
          <span className="text-[10px] uppercase font-bold text-slate-400 block">Evaluated Students</span>
          <strong className="text-xl font-black text-slate-900">{s.total_evaluated || 0}</strong>
        </div>

        <div className="bg-white p-3.5 rounded-xl border border-slate-200">
          <span className="text-[10px] uppercase font-bold text-slate-400 block">Highest Score</span>
          <strong className="text-xl font-black text-emerald-600">{s.highest_marks || 0}</strong>
        </div>

        <div className="bg-white p-3.5 rounded-xl border border-slate-200">
          <span className="text-[10px] uppercase font-bold text-slate-400 block">Average Score</span>
          <strong className="text-xl font-black text-blue-600">{s.average_marks || 0}</strong>
        </div>

        <div className="bg-white p-3.5 rounded-xl border border-slate-200">
          <span className="text-[10px] uppercase font-bold text-slate-400 block">Lowest Score</span>
          <strong className="text-xl font-black text-slate-700">{s.lowest_marks || 0}</strong>
        </div>

        <div className="bg-white p-3.5 rounded-xl border border-slate-200">
          <span className="text-[10px] uppercase font-bold text-slate-400 block">Pass Rate</span>
          <strong className="text-xl font-black text-emerald-600">{s.pass_percentage || 0}%</strong>
        </div>

        <div className="bg-white p-3.5 rounded-xl border border-slate-200">
          <span className="text-[10px] uppercase font-bold text-slate-400 block">Qualified (Passed)</span>
          <strong className="text-xl font-black text-slate-900">{s.pass_count || 0} / {s.total_evaluated || 0}</strong>
        </div>
      </div>

      {/* Merit List Preview Table */}
      <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div className="p-4 border-b border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3">
          <div className="flex items-center gap-2">
            <Award className="w-5 h-5 text-amber-500" />
            <h3 className="text-sm font-bold text-slate-900">Merit List Roster</h3>
          </div>

          <div className="relative w-full sm:w-64">
            <Search className="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" />
            <input
              type="text"
              placeholder="Search candidate or reg no..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-8 pr-3 py-1.5 text-xs border border-slate-200 rounded-lg"
            />
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs border-collapse">
            <thead>
              <tr className="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase text-[10px] font-bold">
                <th className="py-3 px-3 text-center">Overall Merit</th>
                <th className="py-3 px-3 text-center">Zone Merit</th>
                <th className="py-3 px-3 text-center">Center Merit</th>
                <th className="py-3 px-4">Candidate Information</th>
                <th className="py-3 px-4">Assigned Location</th>
                <th className="py-3 px-4">Final Score</th>
                <th className="py-3 px-4">Result Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
              {loading ? (
                <tr>
                  <td colSpan={7} className="py-8 text-center text-slate-400">Computing merit rankings...</td>
                </tr>
              ) : filteredMerit.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-8 text-center text-slate-400">No evaluated records for this examination.</td>
                </tr>
              ) : (
                filteredMerit.map((item, idx) => (
                  <tr key={idx} className="hover:bg-slate-50/60">
                    <td className="py-3 px-3 text-center">
                      <span className={`inline-flex items-center justify-center w-7 h-7 rounded-full font-black text-xs ${item.overall_merit === 1 ? 'bg-amber-100 text-amber-800 ring-2 ring-amber-300' : item.overall_merit <= 3 ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-700'}`}>
                        {item.overall_merit}
                      </span>
                    </td>
                    <td className="py-3 px-3 text-center font-bold text-slate-600">
                      #{item.zone_merit}
                    </td>
                    <td className="py-3 px-3 text-center font-bold text-slate-600">
                      #{item.center_merit}
                    </td>
                    <td className="py-3 px-4">
                      <div className="font-bold text-slate-900">{item.student_name}</div>
                      <div className="text-[11px] font-mono font-bold text-blue-600">Reg: {item.registration_number}</div>
                      <div className="text-[10px] text-slate-400">{item.institution}</div>
                    </td>
                    <td className="py-3 px-4">
                      <div className="font-bold text-slate-800">{item.center_name}</div>
                      <div className="text-[10px] text-slate-400">{item.zone_name}</div>
                    </td>
                    <td className="py-3 px-4">
                      <div className="font-black text-sm text-slate-900">{item.obtained_marks}</div>
                      <div className="text-[10px] text-slate-400">{item.percentage}%</div>
                    </td>
                    <td className="py-3 px-4">
                      {item.is_passed ? (
                        <span className="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                          PASSED
                        </span>
                      ) : (
                        <span className="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-200">
                          FAILED
                        </span>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
