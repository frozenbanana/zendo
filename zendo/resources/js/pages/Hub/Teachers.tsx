import { Head } from '@inertiajs/react';

interface Props {
    teachers: { data: { id: string; name: string; bio: string | null; photo: string | null; specialties: string[] | null }[] };
}

export default function HubTeachers({ teachers }: Props) {
    return (
        <>
            <Head title="Teachers" />

            <div className="max-w-6xl mx-auto px-4 py-12">
                <h1 className="text-3xl font-bold tracking-tight mb-2">
                    Our Teachers
                </h1>
                <p className="text-gray-600 mb-8">
                    Meet the guides who lead our retreats.
                </p>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {teachers.data.map((teacher) => (
                        <div key={teacher.id} className="border rounded-lg p-6 hover:shadow-lg transition-shadow">
                            <h2 className="text-xl font-semibold mb-2">{teacher.name}</h2>
                            {teacher.bio && (
                                <p className="text-sm text-gray-600 mb-3">{teacher.bio}</p>
                            )}
                            {teacher.specialties && teacher.specialties.length > 0 && (
                                <div className="flex gap-1 flex-wrap">
                                    {teacher.specialties.map((specialty) => (
                                        <span key={specialty} className="text-xs bg-indigo-100 text-indigo-800 px-2 py-0.5 rounded">
                                            {specialty}
                                        </span>
                                    ))}
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}