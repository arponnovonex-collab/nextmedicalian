import React, { useState, useEffect, useRef } from 'react';
import api from '../../api/axios';
import { Settings, Save, CheckCircle2, Upload, Trash2, FileSignature } from 'lucide-react';

const DEFAULT_INSTRUCTIONS = `১. পরীক্ষা শুরুর অন্তত ৩০ মিনিট আগে নির্ধারিত ভেন্যুতে উপস্থিত হতে হবে।
২. উত্তরপত্র পূরণের জন্য কালো বলপয়েন্ট কলম সঙ্গে আনতে হবে।
৩. পরীক্ষার হলে মোবাইল ফোন, ক্যালকুলেটর, স্মার্টওয়াচ কিংবা কোনো ধরনের ইলেকট্রনিক ডিভাইস ব্যবহার করা যাবে না। এনে থাকলে তা পরীক্ষা শুরুর পূর্বেই invigilator এর নিকট জমা দিতে হবে।
৪. OMR শিটে নাম, রোল নম্বর ও প্রয়োজনীয় তথ্য নির্ভুলভাবে পূরণ করতে হবে। রোল নম্বর তোমাদের admit card এ আছে।
৫. উত্তর প্রদানের ক্ষেত্রে OMR শিটের নির্ধারিত বৃত্ত সম্পূর্ণ ভরাট করতে হবে।
৬. পরীক্ষার হলে কোনো ধরনের অসদুপায় অবলম্বন করা যাবে না।
৭. পরীক্ষা শেষ হওয়ার আগে অনুমতি ছাড়া পরীক্ষার হল ত্যাগ করা যাবে না।`;

export default function SettingsPage() {
  const [settings, setSettings] = useState({
    app_name: 'NEXT MEDICALIAN',
    organization_name: 'NEXT MEDICALIAN',
    support_phone: '+8801700000000',
    support_email: 'support@nextmedicalian.com',
    primary_color: '#0F172A',
    admit_card_instructions: DEFAULT_INSTRUCTIONS,
    authorized_signature_title: 'Controller of Examinations',
    authorized_signature_image: '',
  });
  const [loading, setLoading] = useState(true);
  const [saved, setSaved] = useState(false);
  const [uploadingSig, setUploadingSig] = useState(false);
  const fileInputRef = useRef(null);

  useEffect(() => {
    fetchSettings();
  }, []);

  const fetchSettings = async () => {
    try {
      const res = await api.get('/admin/settings');
      if (res.data.success) {
        setSettings(prev => ({
          ...prev,
          ...res.data.data,
          admit_card_instructions: res.data.data.admit_card_instructions || DEFAULT_INSTRUCTIONS,
        }));
      }
    } catch (e) {}
    finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaved(false);
    try {
      const res = await api.put('/admin/settings', settings);
      if (res.data.success) {
        setSaved(true);
        setTimeout(() => setSaved(false), 3000);
      }
    } catch (err) {
      alert('Error updating settings.');
    }
  };

  const handleSignatureUpload = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
      alert('Please select an image file (PNG, JPG, WEBP).');
      return;
    }

    const formData = new FormData();
    formData.append('signature', file);

    setUploadingSig(true);
    try {
      const res = await api.post('/admin/settings/signature', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      if (res.data.success) {
        setSettings(prev => ({ ...prev, authorized_signature_image: res.data.data.signature_url }));
        alert('Signature uploaded successfully!');
      }
    } catch (err) {
      alert('Failed to upload signature. Please try again.');
    } finally {
      setUploadingSig(false);
      if (fileInputRef.current) fileInputRef.current.value = '';
    }
  };

  const handleRemoveSignature = async () => {
    if (!confirm('Are you sure you want to remove the authorized signature?')) return;
    try {
      await api.delete('/admin/settings/signature');
      setSettings(prev => ({ ...prev, authorized_signature_image: '' }));
    } catch (err) {
      alert('Failed to remove signature.');
    }
  };

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">System Configuration & Branding</h1>
        <p className="text-xs text-slate-500 mt-1">Configure portal organization branding, support contact, admit card instructions, and controller signature.</p>
      </div>

      <form onSubmit={handleSubmit} className="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-xs space-y-5">
        {saved && (
          <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs font-bold text-emerald-700 flex items-center gap-2">
            <CheckCircle2 className="w-4 h-4" />
            <span>Settings saved successfully.</span>
          </div>
        )}

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Application Name
            </label>
            <input
              type="text"
              required
              value={settings.app_name}
              onChange={(e) => setSettings({ ...settings, app_name: e.target.value })}
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
            />
          </div>

          <div>
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Organization Name
            </label>
            <input
              type="text"
              required
              value={settings.organization_name}
              onChange={(e) => setSettings({ ...settings, organization_name: e.target.value })}
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
            />
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Support Phone Number
            </label>
            <input
              type="text"
              value={settings.support_phone}
              onChange={(e) => setSettings({ ...settings, support_phone: e.target.value })}
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
            />
          </div>

          <div>
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Support Email
            </label>
            <input
              type="email"
              value={settings.support_email}
              onChange={(e) => setSettings({ ...settings, support_email: e.target.value })}
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
            />
          </div>
        </div>

        <div>
          <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Default Admit Card Instructions (PDF)
          </label>
          <textarea
            rows={7}
            value={settings.admit_card_instructions}
            onChange={(e) => setSettings({ ...settings, admit_card_instructions: e.target.value })}
            className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl font-sans"
            placeholder="Enter instructions for examinees..."
          />
          <p className="text-[11px] text-slate-500 mt-1">
            This text will appear in the admit card under "Important Instructions". Every point will be cleanly rendered on the downloaded PDF.
          </p>
        </div>

        {/* Signature Upload Section */}
        <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
          <div className="flex items-center justify-between">
            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                Authorized Controller Signature (স্বাক্ষর আপলোড)
              </label>
              <p className="text-[11px] text-slate-500">Upload signature image (PNG/JPG transparent or white background) for the Admit Card.</p>
            </div>
            {settings.authorized_signature_image && (
              <button
                type="button"
                onClick={handleRemoveSignature}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
              >
                <Trash2 className="w-3.5 h-3.5" /> Remove Signature
              </button>
            )}
          </div>

          <div className="flex items-center gap-4">
            {settings.authorized_signature_image ? (
              <div className="p-2 bg-white border border-slate-200 rounded-lg shadow-2xs">
                <img
                  src={settings.authorized_signature_image}
                  alt="Controller Signature"
                  className="h-12 max-w-[180px] object-contain"
                />
              </div>
            ) : (
              <div className="h-12 w-36 border border-dashed border-slate-300 rounded-lg flex items-center justify-center text-[10px] text-slate-400 font-semibold bg-white">
                No Signature Uploaded
              </div>
            )}

            <div className="flex items-center gap-2">
              <input
                type="file"
                ref={fileInputRef}
                onChange={handleSignatureUpload}
                accept="image/png,image/jpeg,image/webp"
                className="hidden"
                id="signature-file-input"
              />
              <label
                htmlFor="signature-file-input"
                className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 rounded-xl text-xs font-semibold cursor-pointer shadow-2xs transition-colors"
              >
                <Upload className="w-3.5 h-3.5" />
                {uploadingSig ? 'Uploading...' : 'Choose Signature Image'}
              </label>
            </div>
          </div>
        </div>

        <div>
          <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Authorized Signature Title
          </label>
          <input
            type="text"
            value={settings.authorized_signature_title}
            onChange={(e) => setSettings({ ...settings, authorized_signature_title: e.target.value })}
            className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
            placeholder="Controller of Examinations"
          />
        </div>

        <div className="flex justify-end pt-3 border-t border-slate-100">
          <button
            type="submit"
            className="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm transition-colors cursor-pointer"
          >
            <Save className="w-4 h-4" /> Save Settings
          </button>
        </div>
      </form>
    </div>
  );
}
