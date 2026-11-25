import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import axios from 'axios';
import { CheckCircle2, ShieldAlert, Sparkles, Building, Calendar, ArrowLeft } from 'lucide-react';

export default function VerifyAdmitCard() {
  const { token } = useParams();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  useEffect(() => {
    axios.get(`/api/public/verify/admit/${token}`)
      .then(res => {
        if (res.data.success) {
          setData(res.data.data);
        } else {
          setError(true);
        }
      })
      .catch(() => setError(true))
      .finally(() => setLoading(false));
  }, [token]);

  return (
    <div className="min-h-screen bg-slate-900 flex flex-col justify-center items-center p-4">
      <div className="w-full max-w-md bg-white rounded-3xl shadow-2xl p-6 sm:p-8 space-y-6">
        <div className="text-center">
          <div className="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 text-blue-600 rounded-full text-[10px] font-bold uppercase tracking-widest mb-3">
            <Sparkles className="w-3.5 h-3.5" /> EdGenie Verification
          </div>
          <h2 className="text-xl font-extrabold text-slate-900 tracking-tight">Admit Card Verification</h2>
          <p className="text-xs text-slate-500 mt-0.5">NEXT MEDICALIAN Central Examination Management System</p>
        </div>

        {loading ? (
          <div className="py-12 text-center">
            <div className="w-8 h-8 border-4 border-slate-200 border-t-blue-600 rounded-full animate-spin mx-auto mb-2"></div>
            <p className="text-xs text-slate-400">Verifying security token...</p>
          </div>
        ) : error || !data ? (
          <div className="p-6 bg-rose-50 border border-rose-200 rounded-2xl text-center space-y-2">
            <ShieldAlert className="w-10 h-10 text-rose-600 mx-auto" />
            <h3 className="text-base font-bold text-rose-900">Verification Failed</h3>
            <p className="text-xs text-rose-700">This Admit Card verification token is invalid, revoked, or unrecognized.</p>
          </div>
        ) : (
          <div className="space-y-4">
            <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center gap-3">
              <CheckCircle2 className="w-8 h-8 text-emerald-600 shrink-0" />
              <div>
                <h4 className="text-xs font-bold text-emerald-900 uppercase tracking-wider">Officially Verified Authentic</h4>
                <p className="text-[11px] text-emerald-700">Authorized candidate admit card issued by examination board.</p>
              </div>
            </div>

            <div className="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-xs space-y-2.5">
              <div className="flex justify-between">
                <span className="text-slate-500">Candidate Name:</span>
                <strong className="text-slate-900">{data.candidate_masked_name}</strong>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Registration Number:</span>
                <strong className="text-blue-600 font-mono text-sm">{data.registration_number}</strong>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Examination:</span>
                <strong className="text-slate-900 text-right">{data.examination_title}</strong>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Exam Date:</span>
                <strong className="text-slate-900">{data.examination_date}</strong>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Center:</span>
                <strong className="text-slate-900 text-right">{data.examination_center} ({data.assigned_zone})</strong>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Seating:</span>
                <strong className="text-slate-900">{data.room_number} &bull; Seat {data.seat_number}</strong>
              </div>
            </div>
          </div>
        )}

        <div className="text-center pt-2">
          <Link to="/login" className="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-800">
            <ArrowLeft className="w-3.5 h-3.5" /> Return to Sign In
          </Link>
        </div>
      </div>
    </div>
  );
}
