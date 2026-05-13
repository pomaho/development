import { usePage } from '@inertiajs/react';
import GuestLayout from '../../Layouts/GuestLayout';

type Props = {
    links: {
        login: string;
        register: string;
    };
};

type PageProps = {
    errors?: Record<string, string>;
};

export default function Register({ links }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    const { props } = usePage<PageProps>();

    return (
        <GuestLayout title="Регистрация">
            <div className="mx-auto max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <form action={links.register} className="space-y-4" method="post">
                    <input name="_token" type="hidden" value={csrf} />
                    <input className="w-full rounded border-slate-300" name="name" placeholder="Имя" required />
                    {props.errors?.name ? <div className="text-xs text-red-700">{props.errors.name}</div> : null}
                    <input className="w-full rounded border-slate-300" name="email" placeholder="Email" required type="email" />
                    {props.errors?.email ? <div className="text-xs text-red-700">{props.errors.email}</div> : null}
                    <input className="w-full rounded border-slate-300" name="password" placeholder="Пароль" required type="password" />
                    {props.errors?.password ? <div className="text-xs text-red-700">{props.errors.password}</div> : null}
                    <input className="w-full rounded border-slate-300" name="password_confirmation" placeholder="Повтор пароля" required type="password" />
                    <button className="w-full rounded bg-blue-700 px-4 py-2 font-medium text-white hover:bg-blue-800" type="submit">Создать аккаунт</button>
                </form>
                <div className="mt-4 text-center text-sm">
                    <a className="text-blue-700 hover:text-blue-900" href={links.login}>Уже есть аккаунт</a>
                </div>
            </div>
        </GuestLayout>
    );
}
